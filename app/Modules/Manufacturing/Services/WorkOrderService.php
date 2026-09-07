<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\InventoryLedger;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Header SPK (Surat Perintah Kerja) — menyatukan seluruh rantai produksi
 * 1 batch/style (knitting -> processing -> cutting -> stitching -> finishing)
 * dan mengakumulasi biaya WIP-nya. TIDAK ada di Anthrilo secara eksplisit
 * (Anthrilo hanya punya production_plans/analitik) — ini tabel baru yang
 * dibutuhkan supaya WIP bisa direkap per batch produksi utk kebutuhan akuntansi
 * (lihat §2 MANUFACTURING_INTEGRATION.md).
 */
class WorkOrderService
{
    public static function create(array $data): WorkOrder
    {
        $spkNumber = DocumentSequence::generateSecure(
            'mfg_work_orders', 'spk_number', 'MFG-' . now()->format('Ymd') . '-'
        );

        return WorkOrder::create([
            'spk_number'      => $spkNumber,
            'order_date'      => $data['order_date'],
            'product_id'      => $data['product_id'] ?? null,
            'style_sku'       => $data['style_sku'] ?? null,
            'garment_name'    => $data['garment_name'] ?? null,
            'planned_qty'     => $data['planned_qty'] ?? 0,
            'size_breakdown'  => $data['size_breakdown'] ?? null,
            'target_date'     => $data['target_date'] ?? null,
            'status'          => 'DRAFT',
            'wip_account_code' => config('coa.wip_produksi'),
            'created_by'      => $data['created_by'] ?? null,
            'remarks'         => $data['remarks'] ?? null,
        ]);
    }

    /**
     * Dipanggil oleh KnitOrderService/ProcessingOrderService/CuttingOrderService/
     * StitchingOrderService setiap kali sebuah jurnal WIP diposting, supaya
     * total_material_cost/total_process_cost/total_wip_cost di SPK selalu
     * sinkron TANPA perlu re-agregasi ulang seluruh tabel anak saat SPK selesai.
     *
     * Dipanggil di DALAM transaction milik caller (bukan membuka transaction baru).
     */
    public static function accumulateCost(int $workOrderId, float $materialCost = 0, float $processCost = 0, float $wastageCost = 0): void
    {
        $wo = WorkOrder::lockForUpdate()->find($workOrderId);
        if (!$wo) {
            return; // knit/processing/cutting/stitching order boleh berdiri sendiri tanpa SPK induk
        }

        $wo->total_material_cost += $materialCost;
        $wo->total_process_cost  += $processCost;
        $wo->total_wip_cost      = ($wo->total_wip_cost + $materialCost + $processCost) - $wastageCost;
        $wo->save();
    }

    /**
     * JURNAL #6 — Penyelesaian SPK (WIP -> Barang Jadi):
     *   Debit  Persediaan Barang Jadi (config('coa.persediaan_barang_jadi'))
     *   Kredit WIP Produksi (config('coa.wip_produksi'))
     *
     * Barang jadi masuk ke tabel `products` yang sama dengan barang dagang biasa
     * (moving average cost digabung, persis pola PurchaseOrderService), supaya
     * laporan stok & HPP tetap satu sumber kebenaran.
     *
     * @param int $workOrderId
     * @param int $productId    SKU tujuan di tabel `products`
     * @param float $qtyFinished Jumlah pcs barang jadi hasil akhir (dari finishing_stages terakhir)
     */
    public function complete(int $workOrderId, int $productId, float $qtyFinished, string $completionDate): WorkOrder
    {
        if ($qtyFinished <= 0) {
            throw new Exception('Qty barang jadi harus lebih dari 0 untuk menyelesaikan SPK.');
        }

        return DB::transaction(function () use ($workOrderId, $productId, $qtyFinished, $completionDate) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($workOrderId);

            if ($wo->status === 'COMPLETED') {
                throw new Exception("SPK '{$wo->spk_number}' sudah COMPLETED, tidak bisa diselesaikan ulang.");
            }

            $existing = JournalHeader::where('evidence_number', $wo->spk_number)
                ->where('transaction_type', 'Work Order Completion (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("SPK '{$wo->spk_number}' sudah memiliki jurnal penyelesaian.");
            }

            $totalWipCost = (float) $wo->total_wip_cost;
            if ($totalWipCost <= 0) {
                throw new Exception("Total biaya WIP SPK '{$wo->spk_number}' adalah 0 — pastikan tahap knitting/processing/cutting/stitching sudah diposting.");
            }

            $unitCost = $totalWipCost / $qtyFinished;

            $product = Product::lockForUpdate()->findOrFail($productId);
            $oldStock = (float) $product->stock_quantity;
            $oldMac   = (float) $product->average_cost;
            $newStock = $oldStock + $qtyFinished;
            $newValue = ($oldStock * $oldMac) + $totalWipCost;
            $newMac   = $newStock > 0 ? ($newValue / $newStock) : 0;

            $product->stock_quantity = $newStock;
            $product->average_cost   = $newMac;
            $product->save();

            $now = now();
            InventoryLedger::create([
                'transaction_date'    => $completionDate,
                'evidence_number'     => $wo->spk_number,
                'product_id'          => $product->id,
                'type'                => 'IN',
                'qty'                 => $qtyFinished,
                'unit_cost'           => $unitCost,
                'total_cost'          => $totalWipCost,
                'running_qty'         => $newStock,
                'running_value'       => $newValue,
                'moving_average_cost' => $newMac,
                'description'         => "Penyelesaian SPK Manufaktur: {$wo->spk_number} - {$wo->garment_name}",
            ]);

            $journal = JournalHeader::create([
                'transaction_date' => $completionDate,
                'evidence_number'  => $wo->spk_number,
                'description'      => "Penyelesaian SPK: {$wo->spk_number} - {$wo->garment_name} ({$qtyFinished} pcs)",
                'transaction_type' => 'Work Order Completion (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => $product->inventory_account_code ?: config('coa.persediaan_barang_jadi'), 'helper_code' => null, 'position' => 'DEBET',  'amount' => $totalWipCost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.wip_produksi'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $totalWipCost, 'created_at' => $now, 'updated_at' => $now],
            ];
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception('Jurnal Penyelesaian SPK tidak balance (Debet != Kredit). Ini adalah salah satu "detak jantung" akuntansi (RULES.md) — proses dibatalkan demi keamanan data.');
            }
            JournalDetail::insert($journalRows);

            $wo->status = 'COMPLETED';
            $wo->journal_id = $journal->getKey();
            $wo->save();

            return $wo;
        });
    }

    /**
     * STAGE 7 — Void Penyelesaian SPK: membalik Jurnal #6 + stok barang jadi.
     * Ditolak jika stok barang jadi dari SPK ini sudah sebagian terjual/terpakai
     * (mis. sudah ada Sales Invoice yang mengurangi stok produk tsb sejak SPK
     * selesai) — dicek via stock_quantity produk saat ini vs qty yang masuk dari SPK.
     */
    public function voidCompletion(int $workOrderId): bool
    {
        return DB::transaction(function () use ($workOrderId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($workOrderId);

            if ($wo->status !== 'COMPLETED') {
                throw new Exception("SPK '{$wo->spk_number}' belum COMPLETED, tidak ada penyelesaian yang bisa di-void.");
            }

            $ledger = InventoryLedger::where('evidence_number', $wo->spk_number)
                ->where('type', 'IN')
                ->first();
            if (!$ledger) {
                throw new Exception("Data kartu stok penyelesaian SPK '{$wo->spk_number}' tidak ditemukan.");
            }

            $product = Product::lockForUpdate()->findOrFail($ledger->product_id);
            if ((float) $product->stock_quantity < (float) $ledger->qty) {
                throw new Exception(
                    "Tidak bisa void: stok barang jadi dari SPK '{$wo->spk_number}' sudah sebagian terpakai/terjual. " .
                    "Buat jurnal koreksi manual jika perlu."
                );
            }

            $newStock = (float) $product->stock_quantity - (float) $ledger->qty;
            $newValue = ((float) $product->stock_quantity * (float) $product->average_cost) - (float) $ledger->total_cost;
            $product->stock_quantity = $newStock;
            $product->average_cost = $newStock > 0 ? max(0, $newValue / $newStock) : 0;
            $product->save();

            $ledger->delete();

            JournalDetail::whereIn('journal_id', function ($q) use ($wo) {
                $q->select('journal_id')->from('journal_headers')
                    ->where('evidence_number', $wo->spk_number)
                    ->where('transaction_type', 'Work Order Completion (MFG)');
            })->delete();
            JournalHeader::where('evidence_number', $wo->spk_number)
                ->where('transaction_type', 'Work Order Completion (MFG)')
                ->delete();

            $wo->status = 'FINISHING';
            $wo->journal_id = null;
            $wo->save();

            return true;
        });
    }
}
