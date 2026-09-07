<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Support\DocumentSequence;
use App\Modules\Manufacturing\Models\KnitOrder;
use App\Modules\Manufacturing\Models\YarnIssue;
use App\Modules\Manufacturing\Models\GreyFabricReceipt;
use App\Modules\Manufacturing\Services\KnitOrderService;

class KnitOrderController extends Controller
{
    protected KnitOrderService $service;

    public function __construct(KnitOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Membuat header Knit Order (belum ada jurnal — jurnal baru terjadi
     * saat receiveGreyFabric()).
     */
    public function store(Request $request)
    {
        $request->validate([
            'work_order_id'   => 'required|exists:mfg_work_orders,id',
            'supplier_id'     => 'required|exists:mfg_suppliers,id',
            'fabric_id'       => 'required|exists:mfg_fabrics,id',
            'order_date'      => 'required|date',
            'planned_qty_kg'  => 'required|numeric|min:0.01',
            'target_date'     => 'nullable|date',
            'gsm'             => 'nullable|integer',
        ]);

        try {
            $knitOrderNumber = DocumentSequence::generateSecure(
                'mfg_knit_orders', 'knit_order_number', 'KO-' . now()->format('Ymd') . '-'
            );

            $ko = KnitOrder::create($request->only([
                'work_order_id', 'supplier_id', 'fabric_id', 'order_date',
                'planned_qty_kg', 'target_date', 'gsm', 'remarks',
            ]) + ['knit_order_number' => $knitOrderNumber, 'status' => 'OPEN', 'created_by' => auth()->id()]);

            SystemLog::record('CREATE', 'Manufacturing Knit Order', 'Membuat Knit Order: ' . $ko->knit_order_number);
            return redirect()->route('mfg.work-orders.show', $request->work_order_id)
                ->with('success', "Knit Order {$ko->knit_order_number} dibuat.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal membuat Knit Order: ' . $e->getMessage());
        }
    }

    public function issueYarn(Request $request, $id)
    {
        $request->validate([
            'issue_date'          => 'required|date',
            'items'               => 'required|array|min:1',
            'items.*.yarn_id'     => 'required|exists:mfg_yarns,id',
            'items.*.qty_issued'  => 'required|numeric|min:0.01',
        ]);

        try {
            $knitOrder = KnitOrder::findOrFail($id);
            $this->service->issueYarn((int) $id, $request->issue_date, $request->input('items'));
            return redirect()->route('mfg.work-orders.show', $knitOrder->work_order_id)
                ->with('success', 'Yarn berhasil dikeluarkan ke knitter.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengeluarkan yarn: ' . $e->getMessage());
        }
    }

    public function receiveGreyFabric(Request $request, $id)
    {
        $request->validate([
            'receipt_date'          => 'required|date',
            'qty_received'          => 'required|numeric|min:0.01',
            'qty_rejected'          => 'nullable|numeric|min:0',
            'lot_number'            => 'nullable|string|max:50',
            'gsm_actual'            => 'nullable|integer',
            'knitting_cost_amount'  => 'nullable|numeric|min:0',
        ]);

        try {
            $knitOrder = KnitOrder::findOrFail($id);
            $this->service->receiveGreyFabric((int) $id, $request->only([
                'receipt_date', 'qty_received', 'qty_rejected', 'lot_number',
                'gsm_actual', 'knitting_cost_amount', 'remarks',
            ]) + ['created_by' => auth()->id()]);

            SystemLog::record('POST', 'Manufacturing Knit Order', "Menerima kain grey dari {$knitOrder->knit_order_number}, jurnal WIP diposting.");
            return redirect()->route('mfg.work-orders.show', $knitOrder->work_order_id)
                ->with('success', 'Kain grey diterima. Jurnal Persediaan Bahan Baku Kain telah diposting.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menerima kain grey: ' . $e->getMessage());
        }
    }

    public function voidYarnIssue($id)
    {
        try {
            $issue = YarnIssue::findOrFail($id);
            $knitOrder = KnitOrder::findOrFail($issue->knit_order_id);
            $this->service->voidYarnIssue((int) $id);
            SystemLog::record('VOID', 'Manufacturing Knit Order', "Void Yarn Issue: {$issue->issue_number}");
            return redirect()->route('mfg.work-orders.show', $knitOrder->work_order_id)
                ->with('success', "Yarn Issue {$issue->issue_number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Yarn Issue: ' . $e->getMessage());
        }
    }

    public function voidGreyFabricReceipt($id)
    {
        try {
            $receipt = GreyFabricReceipt::findOrFail($id);
            $knitOrder = KnitOrder::findOrFail($receipt->knit_order_id);
            $this->service->voidGreyFabricReceipt((int) $id);
            SystemLog::record('VOID', 'Manufacturing Knit Order', "Void Grey Fabric Receipt: {$receipt->receipt_number}");
            return redirect()->route('mfg.work-orders.show', $knitOrder->work_order_id)
                ->with('success', "Penerimaan Kain Grey {$receipt->receipt_number} berhasil di-void.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void Grey Fabric Receipt: ' . $e->getMessage());
        }
    }
}
