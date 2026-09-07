<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\ManufacturingProcess;
use App\Modules\Manufacturing\Services\WorkOrderService;
use App\Modules\Manufacturing\Exports\WorkOrderExport;
use Maatwebsite\Excel\Facades\Excel;

class WorkOrderController extends Controller
{
    protected WorkOrderService $service;

    public function __construct(WorkOrderService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $query = WorkOrder::with('product')
            ->when($search, fn($q) => $q->where('spk_number', 'like', "%{$search}%")
                ->orWhere('garment_name', 'like', "%{$search}%")
                ->orWhere('style_sku', 'like', "%{$search}%"))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy('order_date', 'desc');

        if ($request->get('export') === 'excel') {
            return Excel::download(new WorkOrderExport($query), 'Daftar_SPK_' . date('Ymd_His') . '.xlsx');
        }

        $workOrders = $query->paginate(30)->appends($request->query());
        return view('manufacturing.work_order.index', compact('workOrders', 'search', 'status'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        return view('manufacturing.work_order.create', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_date'   => 'required|date',
            'garment_name' => 'required|string|max:255',
            'planned_qty'  => 'required|integer|min:1',
            'product_id'   => 'nullable|exists:products,id',
            'style_sku'    => 'nullable|string|max:100',
            'target_date'  => 'nullable|date',
        ]);

        try {
            $wo = $this->service->create($request->only([
                'order_date', 'product_id', 'style_sku', 'garment_name',
                'planned_qty', 'target_date', 'remarks',
            ]) + ['created_by' => auth()->id()]);

            SystemLog::record('CREATE', 'Manufacturing Work Order', 'Membuat SPK: ' . $wo->spk_number);
            return redirect()->route('mfg.work-orders.show', $wo->id)
                ->with('success', "SPK {$wo->spk_number} berhasil dibuat.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal membuat SPK: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $workOrder = WorkOrder::with([
            'product',
            'knitOrders.supplier', 'knitOrders.fabric', 'knitOrders.yarnIssues.yarn', 'knitOrders.greyFabricReceipts',
            'processingOrders.supplier', 'processingOrders.fabricIssues.fabric', 'processingOrders.fabricReceipts.fabric',
            'cuttingOrders.fabric', 'cuttingOrders.checks', 'cuttingOrders.stitchingOrders.supplier', 'cuttingOrders.stitchingOrders.finishingStages',
            'finishingStages',
            'barcodeLabels',
        ])->findOrFail($id);

        // Data master untuk form-form inline di halaman show
        $yarns = Yarn::where('is_active', true)->orderBy('yarn_code')->get();
        $fabrics = Fabric::where('is_active', true)->orderBy('fabric_code')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('supplier_name')->get();
        $processes = ManufacturingProcess::where('is_active', true)->orderBy('process_name')->get();

        return view('manufacturing.work_order.show', compact('workOrder', 'yarns', 'fabrics', 'suppliers', 'processes'));
    }

    public function complete(Request $request, $id)
    {
        $request->validate([
            'product_id'      => 'required|exists:products,id',
            'qty_finished'    => 'required|numeric|min:1',
            'completion_date' => 'required|date',
        ]);

        try {
            $wo = $this->service->complete((int) $id, (int) $request->product_id, (float) $request->qty_finished, $request->completion_date);
            SystemLog::record('COMPLETE', 'Manufacturing Work Order', "Menyelesaikan SPK: {$wo->spk_number}, {$request->qty_finished} pcs masuk stok.");
            return redirect()->route('mfg.work-orders.show', $id)
                ->with('success', "SPK {$wo->spk_number} selesai. Barang jadi masuk stok & jurnal WIP->Persediaan diposting.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyelesaikan SPK: ' . $e->getMessage());
        }
    }

    public function voidCompletion($id)
    {
        try {
            $this->service->voidCompletion((int) $id);
            SystemLog::record('VOID', 'Manufacturing Work Order', "Void penyelesaian SPK ID {$id}.");
            return redirect()->route('mfg.work-orders.show', $id)
                ->with('success', 'Penyelesaian SPK berhasil di-void. Status kembali ke FINISHING, stok & jurnal dibalik.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void penyelesaian SPK: ' . $e->getMessage());
        }
    }
}
