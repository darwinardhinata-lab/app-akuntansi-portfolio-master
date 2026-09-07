<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Services\StitchingOrderService;
use App\Modules\Manufacturing\Models\StitchingOrder;

class StitchingOrderController extends Controller
{
    protected StitchingOrderService $service;

    public function __construct(StitchingOrderService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'cutting_order_id' => 'required|exists:mfg_cutting_orders,id',
            'work_order_id'    => 'required|exists:mfg_work_orders,id',
            'order_date'       => 'required|date',
            'pieces_issued'    => 'required|integer|min:1',
            'stitching_rate'   => 'required|numeric|min:0.01',
            'supplier_id'      => 'nullable|exists:mfg_suppliers,id',
            'target_date'      => 'nullable|date',
        ]);

        try {
            $so = $this->service->create(
                (int) $request->cutting_order_id, (int) $request->work_order_id,
                $request->only(['order_date', 'pieces_issued', 'stitching_rate', 'supplier_id', 'target_date', 'remarks'])
                    + ['created_by' => auth()->id(), 'size_breakdown' => $request->input('size_breakdown')]
            );

            SystemLog::record('POST', 'Manufacturing Stitching Order', "Stitching Order {$so->stitching_order_number} dibuat, biaya CMT masuk WIP.");
            return redirect()->route('mfg.work-orders.show', $request->work_order_id)
                ->with('success', "Stitching Order {$so->stitching_order_number} dibuat. Biaya jahit telah diposting ke WIP.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat Stitching Order: ' . $e->getMessage());
        }
    }

    public function void($id)
    {
        try {
            $stitchingOrder = StitchingOrder::findOrFail($id);
            $workOrderId = $stitchingOrder->work_order_id;
            $number = $stitchingOrder->stitching_order_number;
            $this->service->void((int) $id);
            SystemLog::record('VOID', 'Manufacturing Stitching Order', "Void Stitching Order: {$number}");
            return redirect()->route('mfg.work-orders.show', $workOrderId)
                ->with('success', "Stitching Order {$number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Stitching Order: ' . $e->getMessage());
        }
    }
}
