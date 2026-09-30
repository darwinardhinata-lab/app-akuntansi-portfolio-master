<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\FinishingStage;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Models\WorkOrder;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Tahap Finishing multi-stage (Anthrilo: garment_finishing, field `stage`).
 * TIDAK ada jurnal di tahap ini — murni tracking fisik pieces_in/pieces_ok/
 * pieces_rejected per stage (washing -> ironing -> QC -> packing), sesuai
 * §3 MANUFACTURING_INTEGRATION.md. Biaya sudah selesai diakumulasi sejak
 * Cutting + Stitching; finishing hanya proses internal tanpa vendor eksternal
 * baru (jika ada vendor finishing eksternal berbayar, tambahkan lewat
 * ProcessingOrderService dgn process_type=FINISHING sebelum tahap ini).
 */
class FinishingStageService
{
    /**
     * @param array $data ['stage','stage_date','pieces_in','pieces_ok','pieces_rejected',
     *                      'size_breakdown','operator','remarks']
     */
    public function record(int $stitchingOrderId, array $data): FinishingStage
    {
        return DB::transaction(function () use ($stitchingOrderId, $data) {
            $stitchingOrder = StitchingOrder::lockForUpdate()->findOrFail($stitchingOrderId);
            if ($stitchingOrder->status !== 'ISSUED') {
                throw new Exception("Finishing hanya dapat dicatat untuk Stitching Order ISSUED, bukan {$stitchingOrder->status}.");
            }

            $stage = $data['stage'];
            $piecesIn = (int) $data['pieces_in'];
            $piecesOk = (int) $data['pieces_ok'];
            $piecesRejected = (int) ($data['pieces_rejected'] ?? 0);
            if ($piecesOk + $piecesRejected > $piecesIn) {
                throw new Exception('Pieces OK dan reject tidak boleh melebihi pieces masuk.');
            }

            $standardStages = ['WASHING', 'IRONING', 'QC', 'PACKING'];
            $previousStages = ['IRONING' => 'WASHING', 'QC' => 'IRONING', 'PACKING' => 'QC'];
            if (in_array($stage, $standardStages, true) && FinishingStage::where('stitching_order_id', $stitchingOrder->id)->where('stage', $stage)->exists()) {
                throw new Exception("Tahap finishing {$stage} sudah dicatat untuk Stitching Order ini.");
            }
            $availablePieces = (int) $stitchingOrder->pieces_issued;
            if (isset($previousStages[$stage])) {
                $previous = FinishingStage::where('stitching_order_id', $stitchingOrder->id)->where('stage', $previousStages[$stage])->first();
                if (! $previous) throw new Exception("Tahap {$previousStages[$stage]} wajib dicatat sebelum {$stage}.");
                $availablePieces = (int) $previous->pieces_ok;
            }
            if ($piecesIn > $availablePieces) {
                throw new Exception("Pieces masuk {$piecesIn} melebihi output tersedia ({$availablePieces} pcs).");
            }

            $stageRecord = FinishingStage::create([
                'stitching_order_id' => $stitchingOrder->id,
                'work_order_id'      => $stitchingOrder->work_order_id,
                'stage'              => $stage,
                'stage_date'         => $data['stage_date'],
                'pieces_in'          => $piecesIn,
                'pieces_ok'          => $piecesOk,
                'pieces_rejected'    => $piecesRejected,
                'size_breakdown'     => $data['size_breakdown'] ?? null,
                'operator'           => $data['operator'] ?? null,
                'remarks'            => $data['remarks'] ?? null,
            ]);

            if ($stage === 'PACKING') {
                $stitchingOrder->status = 'COMPLETED';
                $stitchingOrder->save();

                $workOrder = WorkOrder::lockForUpdate()->find($stitchingOrder->work_order_id);
                if ($workOrder && $workOrder->status === 'STITCHING') {
                    $workOrder->status = 'FINISHING';
                    $workOrder->save();
                }
            }

            return $stageRecord;
        });
    }

    /**
     * Total pieces_ok dari stage PACKING (stage terakhir) milik sebuah SPK —
     * dipakai WorkOrderService::complete() sbg $qtyFinished.
     */
    public function totalFinishedPieces(int $workOrderId): int
    {
        return (int) FinishingStage::where('work_order_id', $workOrderId)
            ->where('stage', 'PACKING')
            ->sum('pieces_ok');
    }
}
