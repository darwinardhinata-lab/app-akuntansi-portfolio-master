<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\StitchingOrder;
use App\Modules\Manufacturing\Services\FinishingStageService;

class FinishingStageController extends Controller
{
    protected FinishingStageService $service;

    public function __construct(FinishingStageService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'stitching_order_id' => 'required|exists:mfg_stitching_orders,id',
            'stage'              => 'required|in:WASHING,IRONING,QC,PACKING,OTHER',
            'stage_date'         => 'required|date',
            'pieces_in'          => 'required|integer|min:0',
            'pieces_ok'          => 'required|integer|min:0',
            'pieces_rejected'    => 'nullable|integer|min:0',
        ]);

        try {
            $stitchingOrder = StitchingOrder::findOrFail($request->stitching_order_id);
            $stage = $this->service->record((int) $request->stitching_order_id, $request->only([
                'stage', 'stage_date', 'pieces_in', 'pieces_ok', 'pieces_rejected', 'operator', 'remarks',
            ]) + ['size_breakdown' => $request->input('size_breakdown')]);

            SystemLog::record('CREATE', 'Manufacturing Finishing Stage', "Tahap {$stage->stage} dicatat utk {$stitchingOrder->stitching_order_number}.");
            return redirect()->route('mfg.work-orders.show', $stitchingOrder->work_order_id)
                ->with('success', "Tahap finishing '{$stage->stage}' berhasil dicatat.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mencatat tahap finishing: ' . $e->getMessage());
        }
    }
}
