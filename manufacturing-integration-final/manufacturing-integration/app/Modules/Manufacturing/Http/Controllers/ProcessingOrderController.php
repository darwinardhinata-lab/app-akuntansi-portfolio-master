<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Support\DocumentSequence;
use App\Modules\Manufacturing\Models\ProcessingOrder;
use App\Modules\Manufacturing\Models\FabricIssue;
use App\Modules\Manufacturing\Models\FabricReceipt;
use App\Modules\Manufacturing\Services\ProcessingOrderService;

class ProcessingOrderController extends Controller
{
    protected ProcessingOrderService $service;

    public function __construct(ProcessingOrderService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_order_id' => 'required|exists:mfg_work_orders,id',
            'supplier_id'   => 'required|exists:mfg_suppliers,id',
            'process_type'  => 'required|in:DYEING,PRINTING,FINISHING',
            'order_date'    => 'required|date',
            'target_date'   => 'nullable|date',
        ]);

        try {
            $orderNumber = DocumentSequence::generateSecure(
                'mfg_processing_orders', 'order_number', 'PRC-' . now()->format('Ymd') . '-'
            );

            $order = ProcessingOrder::create($request->only([
                'work_order_id', 'supplier_id', 'process_type', 'order_date', 'target_date', 'remarks',
            ]) + ['order_number' => $orderNumber, 'status' => 'OPEN', 'created_by' => auth()->id()]);

            SystemLog::record('CREATE', 'Manufacturing Processing Order', 'Membuat Processing Order: ' . $order->order_number);
            return redirect()->route('mfg.work-orders.show', $request->work_order_id)
                ->with('success', "Processing Order {$order->order_number} dibuat.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat Processing Order: ' . $e->getMessage());
        }
    }

    public function issueFabric(Request $request, $id)
    {
        $request->validate([
            'fabric_id'  => 'required|exists:mfg_fabrics,id',
            'qty_issued' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
            'lot_number' => 'nullable|string|max:50',
            'color'      => 'nullable|string|max:100',
        ]);

        try {
            $order = ProcessingOrder::findOrFail($id);
            $this->service->issueFabric(
                (int) $id, (int) $request->fabric_id, (float) $request->qty_issued,
                $request->issue_date, $request->lot_number, $request->color
            );
            return redirect()->route('mfg.work-orders.show', $order->work_order_id)
                ->with('success', 'Kain grey berhasil dikeluarkan ke processor.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengeluarkan kain: ' . $e->getMessage());
        }
    }

    public function receiveFabric(Request $request, $id)
    {
        $request->validate([
            'finished_fabric_id'   => 'required|exists:mfg_fabrics,id',
            'receipt_date'         => 'required|date',
            'qty_received'         => 'required|numeric|min:0.01',
            'qty_rejected'         => 'nullable|numeric|min:0',
            'shrinkage_percent'    => 'nullable|numeric|min:0|max:100',
            'process_cost_amount'  => 'nullable|numeric|min:0',
        ]);

        try {
            $order = ProcessingOrder::findOrFail($id);
            $this->service->receiveFabric((int) $id, $request->only([
                'finished_fabric_id', 'receipt_date', 'qty_received', 'qty_rejected',
                'lot_number', 'color', 'shade_code', 'shrinkage_percent',
                'process_cost_amount', 'remarks',
            ]) + ['created_by' => auth()->id()]);

            SystemLog::record('POST', 'Manufacturing Processing Order', "Menerima kain finished dari {$order->order_number}, jurnal WIP diposting.");
            return redirect()->route('mfg.work-orders.show', $order->work_order_id)
                ->with('success', 'Kain finished diterima. Jurnal Persediaan Bahan Baku Kain telah diposting.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menerima kain finished: ' . $e->getMessage());
        }
    }

    public function voidFabricIssue($id)
    {
        try {
            $issue = FabricIssue::findOrFail($id);
            $order = ProcessingOrder::findOrFail($issue->processing_order_id);
            $this->service->voidFabricIssue((int) $id);
            SystemLog::record('VOID', 'Manufacturing Processing Order', "Void Fabric Issue: {$issue->issue_number}");
            return redirect()->route('mfg.work-orders.show', $order->work_order_id)
                ->with('success', "Fabric Issue {$issue->issue_number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Fabric Issue: ' . $e->getMessage());
        }
    }

    public function voidFabricReceipt($id)
    {
        try {
            $receipt = FabricReceipt::findOrFail($id);
            $order = ProcessingOrder::findOrFail($receipt->processing_order_id);
            $this->service->voidFabricReceipt((int) $id);
            SystemLog::record('VOID', 'Manufacturing Processing Order', "Void Fabric Receipt: {$receipt->receipt_number}");
            return redirect()->route('mfg.work-orders.show', $order->work_order_id)
                ->with('success', "Penerimaan Kain Finished {$receipt->receipt_number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Fabric Receipt: ' . $e->getMessage());
        }
    }
}
