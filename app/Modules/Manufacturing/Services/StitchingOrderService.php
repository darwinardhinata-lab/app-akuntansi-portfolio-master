<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\DocumentSequence;
use App\Support\JournalBalanceValidator;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\FinishingStage;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Tahap Stitching/CMT (Anthrilo: stitching_orders).
 *
 * create() -> JURNAL #5:
 *   Debit  WIP Produksi (config('coa.wip_produksi'))
 *   Kredit Hutang Usaha Maklun (config('coa.hutang_usaha_maklun'))
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

            $stitchingOrderNumber = DocumentSequence::generateSecure(
                'mfg_stitching_orders', 'stitching_order_number', 'SEW-' . now()->format('Ymd') . '-'
            );

            $existing = JournalHeader::where('evidence_number', $stitchingOrderNumber)
                ->where('transaction_type', 'Stitching Order (MFG)')
                ->exists();
            if ($existing) {
                throw new Exception("Nomor SEW '{$stitchingOrderNumber}' sudah memiliki jurnal.");
            }

            $piecesIssued = (int) $data['pieces_issued'];
            $rate = (float) $data['stitching_rate'];
            $totalStitchingCost = $piecesIssued * $rate;

            if ($totalStitchingCost <= 0) {
                throw new Exception('Total biaya stitching harus lebih dari 0 (cek pieces_issued dan stitching_rate).');
            }

            $now = now();
            $journal = JournalHeader::create([
                'transaction_date' => $data['order_date'],
                'evidence_number'  => $stitchingOrderNumber,
                'description'      => "Stitching Order {$stitchingOrderNumber} utk SPK {$workOrder->spk_number} ({$piecesIssued} pcs)",
                'transaction_type' => 'Stitching Order (MFG)',
            ]);

            $journalRows = [
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.wip_produksi'), 'helper_code' => null, 'position' => 'DEBET', 'amount' => $totalStitchingCost, 'created_at' => $now, 'updated_at' => $now],
                ['journal_id' => $journal->getKey(), 'account_code' => config('coa.hutang_usaha_maklun'), 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $totalStitchingCost, 'created_at' => $now, 'updated_at' => $now],
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
     * STAGE 7 — Void Stitching Order: membalik Jurnal #5, kurangi kembali
     * akumulasi biaya WIP SPK. Hanya boleh jika BELUM ada Finishing Stage tercatat.
     */
    public function void(int $stitchingOrderId): bool
    {
        return DB::transaction(function () use ($stitchingOrderId) {
            $stitchingOrder = StitchingOrder::lockForUpdate()->findOrFail($stitchingOrderId);

            if (FinishingStage::where('stitching_order_id', $stitchingOrder->id)->exists()) {
                throw new Exception("Tidak bisa void: sudah ada tahap Finishing tercatat utk Stitching Order ini.");
            }

            JournalDetail::whereIn('journal_id', function ($q) use ($stitchingOrder) {
                $q->select('journal_id')->from('journal_headers')
                    ->where('evidence_number', $stitchingOrder->stitching_order_number)
                    ->where('transaction_type', 'Stitching Order (MFG)');
            })->delete();
            JournalHeader::where('evidence_number', $stitchingOrder->stitching_order_number)
                ->where('transaction_type', 'Stitching Order (MFG)')
                ->delete();

            $workOrderId = $stitchingOrder->work_order_id;
            WorkOrderService::accumulateCost($workOrderId, materialCost: 0, processCost: -1 * (float) $stitchingOrder->total_stitching_cost);

            $stitchingOrder->delete();

            if (StitchingOrder::where('work_order_id', $workOrderId)->doesntExist()) {
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
