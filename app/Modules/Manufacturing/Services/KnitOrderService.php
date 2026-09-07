<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\KnitOrder;
use App\Modules\Manufacturing\Models\YarnIssue;
use App\Modules\Manufacturing\Models\GreyFabricReceipt;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Tahap Knitting (Anthrilo: knit_orders + yarn_issues_to_knitter + grey_fabric_receipts).
 *
 * 1. issueYarn()   -> perpindahan gudang internal (yarn keluar ke knitter),
 *                     TIDAK membuat jurnal keuangan (belum ada nilai tambah/biaya
 *                     final, murni perpindahan fisik — sesuai §3 MANUFACTURING_INTEGRATION.md).
 * 2. receiveGreyFabric() -> JURNAL #2:
 *      Debit  Persediaan Bahan Baku Kain (config('coa.persediaan_bahan_baku_kain'))  = total yarn terpakai + biaya knitting
 *      Kredit Persediaan Bahan Baku Benang (config('coa.persediaan_bahan_baku_benang'))
 *      Kredit Hutang Usaha Maklun (config('coa.hutang_usaha_maklun'))  = biaya jasa knitting
 *
 * CATATAN PENTING soal kenapa BUKAN akun WIP di sini:
 * Kain grey hasil knitting masih berupa stok umum (fungible) yang belum
 * dialokasikan ke SPK/style tertentu — statusnya sama seperti bahan baku
 * biasa, hanya sudah melalui 1 tahap konversi jasa. Akun WIP baru dipakai
 * mulai tahap Cutting (CuttingOrderService), yaitu saat kain benar-benar
 * "dipotong untuk SPK tertentu" dan tidak bisa lagi dialihkan ke style lain.
 * Ini konvensi akuntansi biaya garmen standar, bukan asumsi bebas.
 */
class KnitOrderService
{
    /**
     * @param array $items list of ['yarn_id','qty_issued','lot_number']
     */
    public function issueYarn(int $knitOrderId, string $issueDate, array $items): array
    {
        if (empty($items)) {
            throw new Exception('Yarn issue harus memiliki minimal 1 item.');
        }

        return DB::transaction(function () use ($knitOrderId, $issueDate, $items) {
            $knitOrder = KnitOrder::lockForUpdate()->findOrFail($knitOrderId);
            $now = now();
            $issues = [];

            foreach ($items as $row) {
                $issueNumber = DocumentSequence::generateSecure(
                    'mfg_yarn_issues', 'issue_number', 'YI-' . now()->format('Ymd') . '-'
                );

                $yarn = Yarn::lockForUpdate()->findOrFail($row['yarn_id']);
                $qty = (float) $row['qty_issued'];

                $result = MaterialCostHelper::issueStock(
                    $yarn, 'YARN', $qty, $issueDate, $issueNumber,
                    "Yarn Issue ke Knitter: {$knitOrder->knit_order_number}"
                );

                $issues[] = YarnIssue::create([
                    'issue_number'  => $issueNumber,
                    'issue_date'    => $issueDate,
                    'knit_order_id' => $knitOrder->id,
                    'yarn_id'       => $yarn->id,
                    'lot_number'    => $row['lot_number'] ?? null,
                    'qty_issued'    => $qty,
                    'unit_cost'     => $result['unit_cost'],
                    'total_cost'    => $result['total_cost'],
                    'returned_qty'  => 0,
                ]);
            }

            if ($knitOrder->status === 'OPEN') {
                $knitOrder->status = 'ISSUED';
                $knitOrder->save();
            }

            return $issues;
        });
    }

    /**
     * Menerima kain grey hasil knitting + posting Jurnal #2.
     *
     * @param array $data ['receipt_date','qty_received','qty_rejected','lot_number','gsm_actual',
     *                     'knitting_cost_amount' (tagihan jasa knitting dari vendor), 'created_by']
     */
    public function receiveGreyFabric(int $knitOrderId, array $data): GreyFabricReceipt
    {
        return DB::transaction(function () use ($knitOrderId, $data) {
            $knitOrder = KnitOrder::lockForUpdate()->findOrFail($knitOrderId);
            $fabric = Fabric::lockForUpdate()->findOrFail($knitOrder->fabric_id);

            $receiptNumber = DocumentSequence::generateSecure(
                'mfg_grey_fabric_receipts', 'receipt_number', 'GFR-' . now()->format('Ymd') . '-'
            );

            $existing = JournalHeader::where('evidence_number', $receiptNumber)
                ->where('transaction_type', 'Grey Fabric Receipt (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor GFR '{$receiptNumber}' sudah memiliki jurnal.");
            }

            // Total biaya yarn yang sudah dikeluarkan utk knit order ini (belum pernah direklas ke WIP)
            $yarnCost = (float) YarnIssue::where('knit_order_id', $knitOrder->id)->sum('total_cost');
            $knittingCost = (float) ($data['knitting_cost_amount'] ?? 0);
            $totalWipCost = $yarnCost + $knittingCost;
            $qtyReceived = (float) $data['qty_received'];

            if ($qtyReceived <= 0) {
                throw new Exception('Qty grey fabric yang diterima harus lebih dari 0.');
            }

            $unitCostFabric = $totalWipCost / $qtyReceived;

            // Kain grey masuk sbg stok baru (state=GREY) dgn HPP dari akumulasi biaya WIP
            MaterialCostHelper::receiveStock(
                $fabric, 'FABRIC', $qtyReceived, $unitCostFabric, $data['receipt_date'], $receiptNumber,
                "Hasil Knitting: {$knitOrder->knit_order_number}"
            );

            $now = now();
            $journal = JournalHeader::create([
                'transaction_date' => $data['receipt_date'],
                'evidence_number'  => $receiptNumber,
                'description'      => "Penerimaan Kain Grey dari Knitting Order {$knitOrder->knit_order_number}",
                'transaction_type' => 'Grey Fabric Receipt (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.persediaan_bahan_baku_kain'), 'helper_code' => null, 'position' => 'DEBET', 'amount' => $totalWipCost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.persediaan_bahan_baku_benang'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $yarnCost, 'created_at' => $now, 'updated_at' => $now],
            ];
            if ($knittingCost > 0) {
                $journalRows[] = ['journal_id' => $journal->getKey(), 'account_code' => config('coa.hutang_usaha_maklun'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $knittingCost, 'created_at' => $now, 'updated_at' => $now];
            }
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception("Jurnal tidak balance (Debet != Kredit). Batalkan & cek input.");
            }
            JournalDetail::insert($journalRows);

            $receipt = GreyFabricReceipt::create([
                'receipt_number'       => $receiptNumber,
                'receipt_date'         => $data['receipt_date'],
                'knit_order_id'        => $knitOrder->id,
                'fabric_id'            => $fabric->id,
                'qty_received'         => $qtyReceived,
                'qty_rejected'         => $data['qty_rejected'] ?? 0,
                'lot_number'           => $data['lot_number'] ?? null,
                'gsm_actual'           => $data['gsm_actual'] ?? null,
                'knitting_cost_amount' => $knittingCost,
                'remarks'              => $data['remarks'] ?? null,
                'created_by'           => $data['created_by'] ?? null,
            ]);

            $knitOrder->status = 'COMPLETED';
            $knitOrder->save();

            // CATATAN: total_wip_cost SPK TIDAK diakumulasi di sini — kain grey hasil
            // knitting masih stok umum (Persediaan Bahan Baku Kain), belum masuk WIP.
            // Akumulasi biaya ke SPK baru terjadi mulai CuttingOrderService (lihat catatan
            // di atas kelas KnitOrderService ini). $knitOrder->work_order_id hanya dipakai
            // utk keperluan traceability/perencanaan, bukan pemicu jurnal SPK.

            return $receipt;
        });
    }

    /**
     * STAGE 7 — Void Yarn Issue: kembalikan stok yarn, hapus baris ledger.
     * Hanya boleh jika Knit Order BELUM menerima kain grey (status masih ISSUED) —
     * begitu grey fabric diterima, biaya yarn issue ini sudah "terkunci" di Jurnal #2.
     */
    public function voidYarnIssue(int $yarnIssueId): bool
    {
        return DB::transaction(function () use ($yarnIssueId) {
            $issue = YarnIssue::lockForUpdate()->findOrFail($yarnIssueId);
            $knitOrder = KnitOrder::lockForUpdate()->findOrFail($issue->knit_order_id);

            if ($knitOrder->status !== 'ISSUED') {
                throw new Exception(
                    "Tidak bisa void: Knit Order '{$knitOrder->knit_order_number}' berstatus {$knitOrder->status}. " .
                    "Yarn issue hanya bisa di-void selama kain grey belum diterima."
                );
            }

            $yarn = Yarn::lockForUpdate()->findOrFail($issue->yarn_id);
            $yarn->stock_quantity = (float) $yarn->stock_quantity + (float) $issue->qty_issued;
            $yarn->save();

            MaterialLedger::where('evidence_number', $issue->issue_number)->delete();
            $issue->delete();

            if (YarnIssue::where('knit_order_id', $knitOrder->id)->doesntExist()) {
                $knitOrder->status = 'OPEN';
                $knitOrder->save();
            }

            return true;
        });
    }

    /**
     * STAGE 7 — Void Grey Fabric Receipt: membalik Jurnal #2 + kartu stok kain grey.
     * Ditolak jika kain grey tsb sudah sebagian terpakai (diissue ke processor / dipotong).
     */
    public function voidGreyFabricReceipt(int $receiptId): bool
    {
        return DB::transaction(function () use ($receiptId) {
            $receipt = GreyFabricReceipt::lockForUpdate()->findOrFail($receiptId);
            $knitOrder = KnitOrder::lockForUpdate()->findOrFail($receipt->knit_order_id);
            $fabric = Fabric::lockForUpdate()->findOrFail($receipt->fabric_id);

            if ((float) $fabric->stock_quantity < (float) $receipt->qty_received) {
                throw new Exception(
                    "Tidak bisa void: kain grey hasil GFR '{$receipt->receipt_number}' sudah sebagian terpakai " .
                    "(diissue ke processor atau dipotong). Buat jurnal koreksi manual jika perlu."
                );
            }

            $yarnCost = (float) YarnIssue::where('knit_order_id', $knitOrder->id)->sum('total_cost');
            $totalWipCost = $yarnCost + (float) $receipt->knitting_cost_amount;

            $newStock = (float) $fabric->stock_quantity - (float) $receipt->qty_received;
            $newValue = ((float) $fabric->stock_quantity * (float) $fabric->average_cost) - $totalWipCost;
            $fabric->stock_quantity = $newStock;
            $fabric->average_cost = $newStock > 0 ? max(0, $newValue / $newStock) : 0;
            $fabric->save();

            MaterialLedger::where('evidence_number', $receipt->receipt_number)->delete();

            JournalDetail::whereIn('journal_id', function ($q) use ($receipt) {
                $q->select('journal_id')->from('journal_headers')
                    ->where('evidence_number', $receipt->receipt_number)
                    ->where('transaction_type', 'Grey Fabric Receipt (MFG)');
            })->delete();
            JournalHeader::where('evidence_number', $receipt->receipt_number)
                ->where('transaction_type', 'Grey Fabric Receipt (MFG)')
                ->delete();

            $receipt->delete();

            $knitOrder->status = 'ISSUED';
            $knitOrder->save();

            return true;
        });
    }
}
