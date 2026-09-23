<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\ProcessingOrder;
use App\Modules\Manufacturing\Models\FabricIssue;
use App\Modules\Manufacturing\Models\FabricReceipt;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Tahap Processing/Dyeing/Printing/Finishing kain (Anthrilo: processing_orders +
 * grey_fabric_issues + finished_fabric_receipts).
 *
 * 1. issueFabric()  -> kain GREY keluar gudang menuju processor, tanpa jurnal
 *                      (perpindahan internal, sama pola dgn KnitOrderService::issueYarn).
 * 2. receiveFabric() -> JURNAL #3:
 *      Debit  Persediaan Bahan Baku Kain (kain FINISHED, stok baru)  = nilai kain grey terpakai + biaya proses
 *      Kredit Persediaan Bahan Baku Kain (kain GREY terpakai)
 *      Kredit Hutang Usaha Maklun (biaya jasa dyeing/printing/finishing)
 *    Kain hasil (state=FINISHED) dicatat sbg SKU baru di mfg_fabrics dgn
 *    fabric_code berbeda dari grey-nya (dibuat oleh Controller Stage 3 saat
 *    approve processing order, karena butuh input warna/shade final).
 *
 *    Sama seperti KnitOrderService, TIDAK memakai akun WIP di sini — kain
 *    finished hasil dyeing/printing masih stok umum (belum dipotong utk SPK
 *    tertentu). WIP baru mulai di CuttingOrderService. Lihat catatan lengkap
 *    di KnitOrderService.
 */
class ProcessingOrderService
{
    public function issueFabric(int $processingOrderId, int $fabricId, float $qty, string $issueDate, ?string $lotNumber = null, ?string $color = null): FabricIssue
    {
        return DB::transaction(function () use ($processingOrderId, $fabricId, $qty, $issueDate, $lotNumber, $color) {
            $order = ProcessingOrder::lockForUpdate()->findOrFail($processingOrderId);
            $fabric = Fabric::lockForUpdate()->findOrFail($fabricId);

            $issueNumber = DocumentSequence::generateSecure(
                'mfg_fabric_issues', 'issue_number', 'FI-' . now()->format('Ymd') . '-'
            );

            $result = MaterialCostHelper::issueStock(
                $fabric, 'FABRIC', $qty, $issueDate, $issueNumber,
                "Fabric Issue ke Processor: {$order->order_number}"
            );

            $issue = FabricIssue::create([
                'issue_number'        => $issueNumber,
                'issue_date'          => $issueDate,
                'processing_order_id' => $order->id,
                'fabric_id'           => $fabric->id,
                'qty_issued'          => $qty,
                'unit_cost'           => $result['unit_cost'],
                'total_cost'          => $result['total_cost'],
                'lot_number'          => $lotNumber,
                'color'               => $color,
            ]);

            if ($order->status === 'OPEN') {
                $order->status = 'ISSUED';
                $order->save();
            }

            return $issue;
        });
    }

    /**
     * @param array $data ['receipt_date','finished_fabric_id','qty_received','qty_rejected',
     *                     'lot_number','color','shade_code','shrinkage_percent',
     *                     'process_cost_amount','created_by']
     */
    public function receiveFabric(int $processingOrderId, array $data): FabricReceipt
    {
        return DB::transaction(function () use ($processingOrderId, $data) {
            $order = ProcessingOrder::lockForUpdate()->findOrFail($processingOrderId);

            if ($order->status !== 'ISSUED') {
                throw new Exception(
                    "Tidak bisa menerima kain finished: Processing Order '{$order->order_number}' berstatus {$order->status}. " .
                    "Kain finished hanya bisa diterima sekali, setelah kain grey di-issue (status ISSUED) dan sebelum FR lain diposting."
                );
            }

            $finishedFabric = Fabric::lockForUpdate()->findOrFail($data['finished_fabric_id']);

            $receiptNumber = DocumentSequence::generateSecure(
                'mfg_fabric_receipts', 'receipt_number', 'FR-' . now()->format('Ymd') . '-'
            );

            $existing = JournalHeader::where('evidence_number', $receiptNumber)
                ->where('transaction_type', 'Fabric Receipt (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor FR '{$receiptNumber}' sudah memiliki jurnal.");
            }

            $greyCost = (float) FabricIssue::where('processing_order_id', $order->id)->sum('total_cost');
            $processCost = (float) ($data['process_cost_amount'] ?? 0);
            $totalWipCost = $greyCost + $processCost;
            $qtyReceived = (float) $data['qty_received'];

            if ($qtyReceived <= 0) {
                throw new Exception('Qty kain finished yang diterima harus lebih dari 0.');
            }

            $unitCostFabric = $totalWipCost / $qtyReceived;

            MaterialCostHelper::receiveStock(
                $finishedFabric, 'FABRIC', $qtyReceived, $unitCostFabric, $data['receipt_date'], $receiptNumber,
                "Hasil Processing: {$order->order_number}"
            );

            $now = now();
            $journal = JournalHeader::create([
                'transaction_date' => $data['receipt_date'],
                'evidence_number'  => $receiptNumber,
                'description'      => "Penerimaan Kain Finished dari Processing Order {$order->order_number} ({$order->process_type})",
                'transaction_type' => 'Fabric Receipt (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.persediaan_bahan_baku_kain'), 'helper_code' => null, 'position' => 'DEBET', 'amount' => $totalWipCost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.persediaan_bahan_baku_kain'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $greyCost, 'created_at' => $now, 'updated_at' => $now],
            ];
            if ($processCost > 0) {
                $journalRows[] = ['journal_id' => $journal->getKey(), 'account_code' => config('coa.hutang_usaha_maklun'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $processCost, 'created_at' => $now, 'updated_at' => $now];
            }
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception("Jurnal tidak balance (Debet != Kredit). Batalkan & cek input.");
            }
            JournalDetail::insert($journalRows);

            $receipt = FabricReceipt::create([
                'receipt_number'       => $receiptNumber,
                'receipt_date'         => $data['receipt_date'],
                'processing_order_id'  => $order->id,
                'fabric_id'            => $finishedFabric->id,
                'qty_received'         => $qtyReceived,
                'qty_rejected'         => $data['qty_rejected'] ?? 0,
                'lot_number'           => $data['lot_number'] ?? null,
                'color'                => $data['color'] ?? null,
                'shade_code'           => $data['shade_code'] ?? null,
                'shrinkage_percent'    => $data['shrinkage_percent'] ?? 0,
                'process_cost_amount'  => $processCost,
                'remarks'              => $data['remarks'] ?? null,
                'created_by'           => $data['created_by'] ?? null,
            ]);

            $order->status = 'COMPLETED';
            $order->save();

            // CATATAN: sama seperti KnitOrderService, total_wip_cost SPK TIDAK diakumulasi
            // di sini. Kain finished hasil processing masih stok umum di Persediaan Bahan
            // Baku Kain, belum tied ke SPK tertentu. Akumulasi WIP dimulai di CuttingOrderService.

            return $receipt;
        });
    }

    /**
     * STAGE 7 — Void Fabric Issue (kain grey yg dikirim ke processor): kembalikan
     * stok kain grey. Hanya boleh jika Processing Order belum menerima kain finished.
     */
    public function voidFabricIssue(int $fabricIssueId): bool
    {
        return DB::transaction(function () use ($fabricIssueId) {
            $issue = FabricIssue::lockForUpdate()->findOrFail($fabricIssueId);
            $order = ProcessingOrder::lockForUpdate()->findOrFail($issue->processing_order_id);

            if ($order->status !== 'ISSUED') {
                throw new Exception(
                    "Tidak bisa void: Processing Order '{$order->order_number}' berstatus {$order->status}. " .
                    "Fabric issue hanya bisa di-void selama kain finished belum diterima."
                );
            }

            $fabric = Fabric::lockForUpdate()->findOrFail($issue->fabric_id);
            $fabric->stock_quantity = (float) $fabric->stock_quantity + (float) $issue->qty_issued;
            $fabric->save();

            MaterialLedger::where('evidence_number', $issue->issue_number)->delete();
            $issue->delete();

            if (FabricIssue::where('processing_order_id', $order->id)->doesntExist()) {
                $order->status = 'OPEN';
                $order->save();
            }

            return true;
        });
    }

    /**
     * STAGE 7 — Void Fabric Receipt (kain finished): membalik Jurnal #3 + kartu stok.
     * Ditolak jika kain finished tsb sudah sebagian terpakai (dipotong/cutting).
     */
    public function voidFabricReceipt(int $receiptId): bool
    {
        return DB::transaction(function () use ($receiptId) {
            $receipt = FabricReceipt::lockForUpdate()->findOrFail($receiptId);
            $order = ProcessingOrder::lockForUpdate()->findOrFail($receipt->processing_order_id);
            $fabric = Fabric::lockForUpdate()->findOrFail($receipt->fabric_id);

            if ((float) $fabric->stock_quantity < (float) $receipt->qty_received) {
                throw new Exception(
                    "Tidak bisa void: kain finished hasil FR '{$receipt->receipt_number}' sudah sebagian terpakai " .
                    "(dipotong/cutting). Buat jurnal koreksi manual jika perlu."
                );
            }

            $greyCost = (float) FabricIssue::where('processing_order_id', $order->id)->sum('total_cost');
            $totalWipCost = $greyCost + (float) $receipt->process_cost_amount;

            $newStock = (float) $fabric->stock_quantity - (float) $receipt->qty_received;
            $newValue = ((float) $fabric->stock_quantity * (float) $fabric->average_cost) - $totalWipCost;
            $fabric->stock_quantity = $newStock;
            $fabric->average_cost = $newStock > 0 ? max(0, $newValue / $newStock) : 0;
            $fabric->save();

            MaterialLedger::where('evidence_number', $receipt->receipt_number)->delete();

            JournalDetail::whereIn('journal_id', function ($q) use ($receipt) {
                $q->select('journal_id')->from('journal_headers')
                    ->where('evidence_number', $receipt->receipt_number)
                    ->where('transaction_type', 'Fabric Receipt (MFG)');
            })->delete();
            JournalHeader::where('evidence_number', $receipt->receipt_number)
                ->where('transaction_type', 'Fabric Receipt (MFG)')
                ->delete();

            $receipt->delete();

            $order->status = 'ISSUED';
            $order->save();

            return true;
        });
    }
}

