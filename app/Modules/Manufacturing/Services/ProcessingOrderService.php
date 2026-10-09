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
 *    Sama seperti KnitOrderService, TIDAK memakai akun WIP di sini ΓÇö kain
 *    finished hasil dyeing/printing masih stok umum (belum dipotong utk SPK
 *    tertentu). WIP baru mulai di CuttingOrderService. Lihat catatan lengkap
 *    di KnitOrderService.
 */
class ProcessingOrderService
{
    public function issueFabric(int $processingOrderId, int $fabricId, float $qty, string $issueDate, ?string $lotNumber = null, ?string $color = null): FabricIssue
    {
        \App\Support\MaklunIssueGuard::enabled();
        return DB::transaction(function () use ($processingOrderId, $fabricId, $qty, $issueDate, $lotNumber, $color) {
            \App\Support\AccountingPeriodGuard::source([$issueDate]);
            $order = ProcessingOrder::lockForUpdate()->findOrFail($processingOrderId);
            $fabric = Fabric::lockForUpdate()->findOrFail($fabricId);
            $sourceAccount = \App\Support\MaklunIssueGuard::source($order->status, $qty, $fabric->inventory_account_code);
            if ($fabric->state !== 'GREY') {
                throw new Exception('Issue processing memerlukan fabric GREY.');
            }

            $issueNumber = DocumentSequence::generateSecure(
                'mfg_fabric_issues', 'issue_number', 'FI-' . now()->format('Ymd') . '-'
            );

            $result = MaterialCostHelper::issueStock(
                $fabric, 'FABRIC', $qty, $issueDate, $issueNumber,
                "Fabric Issue ke Processor: {$order->order_number}"
            );

            $issue = FabricIssue::create([
                'source_account_code' => $sourceAccount,
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
        return app(MaklunReceiptService::class)->receive(false, $processingOrderId, $data);
    }

    /**
     * STAGE 7 ΓÇö Void Fabric Issue (kain grey yg dikirim ke processor): kembalikan
     * stok kain grey. Hanya boleh jika Processing Order belum menerima kain finished.
     */
    public function voidFabricIssue(int $fabricIssueId, string $reason = ''): bool
    {
        return app(MaklunIssueReversalService::class)->reverse(false, $fabricIssueId, $reason);
    }

    /**
     * STAGE 7 ΓÇö Void Fabric Receipt (kain finished): membalik Jurnal #3 + kartu stok.
     * Ditolak jika kain finished tsb sudah sebagian terpakai (dipotong/cutting).
     */
    public function voidFabricReceipt(int $receiptId, string $reason = ''): bool
    {
        return app(MaklunReceiptService::class)->reverse(false, $receiptId, $reason);
    }
}
