<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\CuttingCheck;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\FinishingStage;
use App\Modules\Platform\Support\CompanyCoaResolver;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Tahap Stitching/CMT (Anthrilo: stitching_orders).
 *
 * create() -> JURNAL #5:
 *   Debit  Barang dalam proses (mapping MGI: 114002)
 *   Kredit Akrual subcontractor/CMT (mapping MGI: 212001)
 *
 * Berbeda dari Cutting, tahap ini TIDAK memindahkan stok bahan (pieces
 * hasil cutting bukan item persediaan bernilai/qty-tracked di sini — hanya
 * cost jasa jahit per pcs yang ditambahkan langsung ke WIP SPK).
 */
class StitchingOrderService
{
    /**
     * @param array $data ['order_date','pieces_issued','size_breakdown','target_date',
     *                      'supplier_id','stitching_rate','remarks','created_by']
     */
    public function create(int $cuttingOrderId, int $workOrderId, array $data): StitchingOrder
    {
        return DB::transaction(function () use ($cuttingOrderId, $workOrderId, $data) {
            $cuttingOrder = CuttingOrder::lockForUpdate()->findOrFail($cuttingOrderId);
            $workOrder = WorkOrder::lockForUpdate()->findOrFail($workOrderId);

            if ($cuttingOrder->status !== 'CHECKED') {
                throw new Exception("Cutting Order {$cuttingOrder->cutting_order_number} belum di-QC (status harus CHECKED sebelum stitching dimulai).");
            }
            if ($cuttingOrder->work_order_id !== $workOrder->id) {
                throw new Exception('Cutting Order harus berasal dari SPK yang sama dengan Stitching Order.');
            }

            $activeCheck = CuttingCheck::query()
                ->where('cutting_order_id', $cuttingOrder->id)
                ->whereNull('voided_at')
                ->latest('id')
                ->first();
            if (! $activeCheck) {
                throw new Exception("Cutting Order {$cuttingOrder->cutting_order_number} belum memiliki hasil QC aktif.");
            }

            $piecesIssued = (int) $data['pieces_issued'];
            $alreadyIssued = (int) StitchingOrder::query()
                ->where('cutting_order_id', $cuttingOrder->id)
                ->whereNotIn('status', ['CANCELED'])
                ->sum('pieces_issued');
            $availablePieces = (int) $activeCheck->pieces_ok - $alreadyIssued;
            if ($piecesIssued > $availablePieces) {
                throw new Exception("Qty Stitching {$piecesIssued} pcs melebihi sisa hasil QC yang layak dijahit ({$availablePieces} pcs).");
            }

            $company = app(OperationalCompany::class)->company();
            $coa = app(CompanyCoaResolver::class);
            $wipAccount = $coa->account($company, 'wip_inventory');
            $subcontractAccrualAccount = $coa->account($company, 'subcontract_accrual');

            $stitchingOrderNumber = DocumentSequence::generateSecure(
                'mfg_stitching_orders', 'stitching_order_number', 'SEW-' . now()->format('Ymd') . '-'
            );

            $existing = JournalHeader::where('evidence_number', $stitchingOrderNumber)
                ->where('transaction_type', 'Stitching Order (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor SEW '{$stitchingOrderNumber}' sudah memiliki jurnal.");
            }

            $rate = (float) $data['stitching_rate'];
            $totalStitchingCost = $piecesIssued * $rate;

            if ($totalStitchingCost <= 0) {
                throw new Exception('Total biaya stitching harus lebih dari 0 (cek pieces_issued dan stitching_rate).');
            }

            $now = now();
            $journal = JournalHeader::create([
                'transaction_date' => $data['order_date'],
                'evidence_number'  => $stitchingOrderNumber,
                'notes'            => "Stitching Order {$stitchingOrderNumber} utk SPK {$workOrder->spk_number} ({$piecesIssued} pcs)",
                'transaction_type' => 'Stitching Order (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $totalStitchingCost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => $subcontractAccrualAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $totalStitchingCost, 'created_at' => $now, 'updated_at' => $now],
            ];
            if (!JournalBalanceValidator::isBalanced($journalRows)) {
                throw new Exception('Jurnal Stitching Order tidak balance (Debet != Kredit).');
            }
            JournalDetail::insert($journalRows);

            $stitchingOrder = StitchingOrder::create([
                'stitching_order_number' => $stitchingOrderNumber,
                'order_date'             => $data['order_date'],
                'cutting_order_id'       => $cuttingOrder->id,
                'work_order_id'          => $workOrder->id,
                'supplier_id'            => $data['supplier_id'] ?? null,
                'pieces_issued'          => $piecesIssued,
                'size_breakdown'         => $data['size_breakdown'] ?? null,
                'target_date'            => $data['target_date'] ?? null,
                'status'                 => 'ISSUED',
                'stitching_rate'         => $rate,
                'total_stitching_cost'   => $totalStitchingCost,
                'journal_id'             => $journal->getKey(),
                'remarks'                => $data['remarks'] ?? null,
                'created_by'             => $data['created_by'] ?? null,
            ]);

            WorkOrderService::accumulateCost($workOrder->id, materialCost: 0, processCost: $totalStitchingCost);

            if ($workOrder->status === 'CUTTING') {
                $workOrder->status = 'STITCHING';
                $workOrder->save();
            }

            return $stitchingOrder;
        });
    }

    /**
     * Void Stitching by posting an append-only reversal; source document and journal are retained.
     */
    public function void(int $stitchingOrderId): bool
    {
        return DB::transaction(function () use ($stitchingOrderId) {
            $stitchingOrder = StitchingOrder::lockForUpdate()->findOrFail($stitchingOrderId);

            if ($stitchingOrder->status === 'CANCELED' || $stitchingOrder->voided_at) {
                throw new Exception('Stitching Order ini sudah dibatalkan sebelumnya.');
            }

            if (FinishingStage::where('stitching_order_id', $stitchingOrder->id)->exists()) {
                throw new Exception("Tidak bisa void: sudah ada tahap Finishing tercatat utk Stitching Order ini.");
            }

            $company = app(OperationalCompany::class)->company();
            $coa = app(CompanyCoaResolver::class);
            $wipAccount = $coa->account($company, 'wip_inventory');
            $subcontractAccrualAccount = $coa->account($company, 'subcontract_accrual');
            $now = now();
            $reversal = JournalHeader::create([
                'transaction_date' => $now->toDateString(),
                'evidence_number' => $stitchingOrder->stitching_order_number . '-VOID',
                'notes' => "Pembalikan Stitching Order {$stitchingOrder->stitching_order_number}",
                'transaction_type' => 'Stitching Order Reversal (MFG)',
            ]);
            $rows = [
                ['journal_id' => $reversal->getKey(), 'account_code' => $subcontractAccrualAccount, 'helper_code' => null, 'position' => 'DEBET', 'amount' => $stitchingOrder->total_stitching_cost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $reversal->getKey(), 'account_code' => $wipAccount, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $stitchingOrder->total_stitching_cost, 'created_at' => $now, 'updated_at' => $now],
            ];
            if (! JournalBalanceValidator::isBalanced($rows)) {
                throw new Exception('Jurnal pembalikan Stitching Order tidak balance (Debet != Kredit).');
            }
            JournalDetail::insert($rows);

            $workOrderId = $stitchingOrder->work_order_id;
            WorkOrderService::accumulateCost($workOrderId, materialCost: 0, processCost: -1 * (float) $stitchingOrder->total_stitching_cost);

            $stitchingOrder->status = 'CANCELED';
            $stitchingOrder->voided_at = $now;
            $stitchingOrder->reversal_journal_id = $reversal->getKey();
            $stitchingOrder->save();

            if (StitchingOrder::where('work_order_id', $workOrderId)->where('status', '!=', 'CANCELED')->doesntExist()) {
                $workOrder = WorkOrder::lockForUpdate()->find($workOrderId);
                if ($workOrder && $workOrder->status === 'STITCHING') {
                    $workOrder->status = 'CUTTING';
                    $workOrder->save();
                }
            }

            return true;
        });
    }
}
