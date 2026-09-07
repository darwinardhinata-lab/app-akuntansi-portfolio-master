<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\BarcodeLabelService;

class BarcodeLabelController extends Controller
{
    protected BarcodeLabelService $service;

    public function __construct(BarcodeLabelService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id'  => 'required|exists:mfg_work_orders,id',
            'batch_number'   => 'required|string|max:50',
            'sizes'          => 'required|array|min:1',
            'sizes.*.size'   => 'required|string|max:20',
            'sizes.*.qty'    => 'required|integer|min:1',
            'sizes.*.mrp'    => 'required|numeric|min:0',
        ]);

        try {
            $labels = $this->service->generate((int) $request->work_order_id, $request->input('sizes'), $request->batch_number);
            $workOrder = WorkOrder::findOrFail($request->work_order_id);

            SystemLog::record('CREATE', 'Manufacturing Barcode Label', count($labels) . " label dicetak utk SPK {$workOrder->spk_number}.");
            return redirect()->route('mfg.work-orders.show', $request->work_order_id)
                ->with('success', count($labels) . ' barcode label berhasil dibuat.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat barcode label: ' . $e->getMessage());
        }
    }

    public function print(Request $request)
    {
        $request->validate(['label_ids' => 'required|array|min:1']);

        try {
            $count = $this->service->markPrinted($request->input('label_ids'));
            return redirect()->back()->with('success', "{$count} label ditandai sudah dicetak.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menandai label: ' . $e->getMessage());
        }
    }
}
