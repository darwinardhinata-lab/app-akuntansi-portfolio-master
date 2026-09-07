<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\CuttingCheck;
use App\Modules\Manufacturing\Services\CuttingOrderService;

class CuttingOrderController extends Controller
{
    protected CuttingOrderService $service;

    public function __construct(CuttingOrderService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id'     => 'required|exists:mfg_work_orders,id',
            'fabric_id'         => 'required|exists:mfg_fabrics,id',
            'order_date'        => 'required|date',
            'fabric_qty_issued' => 'required|numeric|min:0.01',
            'planned_pieces'    => 'required|integer|min:1',
            'marker_efficiency' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $co = $this->service->create(
                (int) $request->work_order_id, (int) $request->fabric_id,
                $request->only(['order_date', 'fabric_qty_issued', 'planned_pieces', 'marker_efficiency', 'remarks'])
                    + ['created_by' => auth()->id(), 'size_breakdown' => $request->input('size_breakdown')]
            );

            SystemLog::record('POST', 'Manufacturing Cutting Order', "Cutting Order {$co->cutting_order_number} dibuat, jurnal WIP diposting.");
            return redirect()->route('mfg.work-orders.show', $request->work_order_id)
                ->with('success', "Cutting Order {$co->cutting_order_number} dibuat. Kain resmi masuk WIP SPK ini.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat Cutting Order: ' . $e->getMessage());
        }
    }

    public function recordCheck(Request $request, $id)
    {
        $request->validate([
            'check_date'        => 'required|date',
            'pieces_cut'        => 'required|integer|min:0',
            'pieces_ok'         => 'required|integer|min:0',
            'pieces_rejected'   => 'nullable|integer|min:0',
            'fabric_used_kg'    => 'nullable|numeric|min:0',
            'fabric_wastage_kg' => 'nullable|numeric|min:0',
        ]);

        try {
            $cuttingOrder = CuttingOrder::findOrFail($id);
            $this->service->recordCheck((int) $id, $request->only([
                'check_date', 'pieces_cut', 'pieces_ok', 'pieces_rejected',
                'fabric_used_kg', 'fabric_wastage_kg', 'checked_by', 'remarks',
            ]));

            SystemLog::record('POST', 'Manufacturing Cutting Check', "QC Cutting Order {$cuttingOrder->cutting_order_number}.");
            return redirect()->route('mfg.work-orders.show', $cuttingOrder->work_order_id)
                ->with('success', 'Hasil QC cutting dicatat. Wastage (jika ada) telah diposting sbg kerugian.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mencatat QC cutting: ' . $e->getMessage());
        }
    }

    public function void($id)
    {
        try {
            $cuttingOrder = CuttingOrder::findOrFail($id);
            $workOrderId = $cuttingOrder->work_order_id;
            $number = $cuttingOrder->cutting_order_number;
            $this->service->void((int) $id);
            SystemLog::record('VOID', 'Manufacturing Cutting Order', "Void Cutting Order: {$number}");
            return redirect()->route('mfg.work-orders.show', $workOrderId)
                ->with('success', "Cutting Order {$number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Cutting Order: ' . $e->getMessage());
        }
    }

    public function voidCheck($id)
    {
        try {
            $check = CuttingCheck::findOrFail($id);
            $cuttingOrder = CuttingOrder::findOrFail($check->cutting_order_id);
            $this->service->voidCheck((int) $id);
            SystemLog::record('VOID', 'Manufacturing Cutting Check', "Void QC Cutting Order: {$cuttingOrder->cutting_order_number}");
            return redirect()->route('mfg.work-orders.show', $cuttingOrder->work_order_id)
                ->with('success', "QC Cutting Order {$cuttingOrder->cutting_order_number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void QC Cutting: ' . $e->getMessage());
        }
    }
}
