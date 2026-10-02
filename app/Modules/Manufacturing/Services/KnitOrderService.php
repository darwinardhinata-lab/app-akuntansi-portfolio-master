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
 *                     final, murni perpindahan fisik ΓÇö sesuai ┬º3 MANUFACTURING_INTEGRATION.md).
 * 2. receiveGreyFabric() -> JURNAL #2:
 *      Debit  Persediaan Bahan Baku Kain (config('coa.persediaan_bahan_baku_kain'))  = total yarn terpakai + biaya knitting
 *      Kredit Persediaan Bahan Baku Benang (config('coa.persediaan_bahan_baku_benang'))
 *      Kredit Hutang Usaha Maklun (config('coa.hutang_usaha_maklun'))  = biaya jasa knitting
 *
 * CATATAN PENTING soal kenapa BUKAN akun WIP di sini:
 * Kain grey hasil knitting masih berupa stok umum (fungible) yang belum
 * dialokasikan ke SPK/style tertentu ΓÇö statusnya sama seperti bahan baku
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
        \App\Support\MaklunIssueGuard::enabled();
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
                $sourceAccount = \App\Support\MaklunIssueGuard::source($knitOrder->status, $row['qty_issued'], $yarn->inventory_account_code, true);

                $result = MaterialCostHelper::issueStock(
                    $yarn, 'YARN', $qty, $issueDate, $issueNumber,
                    "Yarn Issue ke Knitter: {$knitOrder->knit_order_number}"
                );

                $issues[] = YarnIssue::create([
                    'source_account_code' => $sourceAccount,
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
        return app(MaklunReceiptService::class)->receive(true, $knitOrderId, $data);
    }

    /**
     * STAGE 7 ΓÇö Void Yarn Issue: kembalikan stok yarn, hapus baris ledger.
     * Hanya boleh jika Knit Order BELUM menerima kain grey (status masih ISSUED) ΓÇö
     * begitu grey fabric diterima, biaya yarn issue ini sudah "terkunci" di Jurnal #2.
     */
    public function voidYarnIssue(int $yarnIssueId, string $reason = ''): bool
    {
        return app(MaklunIssueReversalService::class)->reverse(true, $yarnIssueId, $reason);
    }

    /**
     * STAGE 7 ΓÇö Void Grey Fabric Receipt: membalik Jurnal #2 + kartu stok kain grey.
     * Ditolak jika kain grey tsb sudah sebagian terpakai (diissue ke processor / dipotong).
     */
    public function voidGreyFabricReceipt(int $receiptId, string $reason = ''): bool
    {
        return app(MaklunReceiptService::class)->reverse(true, $receiptId, $reason);
    }
}
