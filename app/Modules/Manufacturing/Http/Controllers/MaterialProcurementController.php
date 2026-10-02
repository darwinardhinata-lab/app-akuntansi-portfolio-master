<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Models\User;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Http\Request;

class MaterialProcurementController extends Controller
{
    public function __construct(private readonly MaterialProcurementService $service) {}

    public function requestIndex(Request $request)
    {
        $filters = $request->validate([
            'tab' => 'nullable|in:overview,list,mine',
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:DRAFT,SUBMITTED,APPROVED,REJECTED',
            'requester_id' => 'nullable|integer|exists:users,id',
        ]);
        $tab = $filters['tab'] ?? 'overview';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = MaterialPurchaseRequest::query()
            ->with('creator')
            ->withCount('details')
            ->when($tab === 'mine', fn ($query) => $query->where('created_by', auth()->id()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('request_number', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('approval_status', $status))
            ->when($filters['requester_id'] ?? null, fn ($query, $requesterId) => $query->where('created_by', $requesterId))
            ->orderByDesc('id');

        $requests = $query->paginate(30)->withQueryString();
        $requesters = User::query()
            ->whereIn('id', MaterialPurchaseRequest::query()->whereNotNull('created_by')->select('created_by'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $overview = [
            'total' => MaterialPurchaseRequest::count(),
            'draft' => MaterialPurchaseRequest::where('approval_status', MaterialPurchaseRequest::DRAFT)->count(),
            'submitted' => MaterialPurchaseRequest::where('approval_status', MaterialPurchaseRequest::SUBMITTED)->count(),
            'approved' => MaterialPurchaseRequest::where('approval_status', MaterialPurchaseRequest::APPROVED)->count(),
            'rejected' => MaterialPurchaseRequest::where('approval_status', MaterialPurchaseRequest::REJECTED)->count(),
            'mine' => MaterialPurchaseRequest::where('created_by', auth()->id())->count(),
        ];

        return view('manufacturing.material_procurement.request_index', compact(
            'requests', 'requesters', 'overview', 'tab', 'search', 'filters'
        ));
    }

    public function requestShow(int $id)
    {
        $materialRequest = MaterialPurchaseRequest::with([
            'details', 'creator', 'submitter', 'approver', 'rejector',
        ])->findOrFail($id);

        return view('manufacturing.material_procurement.request_show', compact('materialRequest'));
    }

    public function requestCreate()
    {
        return view('manufacturing.material_procurement.request_create', $this->masters());
    }

    public function requestStore(Request $request)
    {
        $data = $this->validateItems($request, false);
        try {
            $pr = $this->service->createRequest($request->only(['request_date', 'required_date', 'remarks']) + ['created_by' => auth()->id()], $data);
            SystemLog::record('CREATE', 'Manufacturing Material Purchase Request', 'Membuat Material PR: '.$pr->request_number);

            return redirect()->route('mfg.material-requests.index')->with('success', 'Material PR '.$pr->request_number.' dibuat sebagai DRAFT.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function requestSubmit(int $id)
    {
        return $this->transition(fn () => $this->service->submitRequest($id, auth()->id()), 'SUBMIT', 'Material PR disubmit.');
    }

    public function requestApprove(int $id)
    {
        return $this->transition(fn () => $this->service->approveRequest($id, auth()->id()), 'APPROVE', 'Material PR disetujui.');
    }

    public function requestReject(Request $request, int $id)
    {
        $request->validate(['rejection_reason' => 'required|string|max:2000']);

        return $this->transition(fn () => $this->service->rejectRequest($id, $request->string('rejection_reason')->toString(), auth()->id()), 'REJECT', 'Material PR ditolak.');
    }

    public function orderIndex(Request $request)
    {
        $orders = MaterialPurchaseOrder::with(['supplier', 'details'])->orderByDesc('id')->paginate(30);

        return view('manufacturing.material_procurement.order_index', compact('orders'));
    }

    public function orderCreate(Request $request)
    {
        $request->validate(['request_id' => 'required|exists:mfg_material_purchase_requests,id']);
        $materialRequest = MaterialPurchaseRequest::with('details')->findOrFail($request->integer('request_id'));
        abort_unless($materialRequest->approval_status === MaterialPurchaseRequest::APPROVED, 409, 'Material PO hanya dari PR APPROVED.');

        return view('manufacturing.material_procurement.order_create', $this->masters() + compact('materialRequest'));
    }

    public function orderStore(Request $request)
    {
        $request->validate(['request_id' => 'required|exists:mfg_material_purchase_requests,id', 'supplier_id' => 'required|exists:mfg_suppliers,id', 'po_date' => 'required|date']);
        $data = $this->validateItems($request, true);
        try {
            $po = $this->service->createOrderFromRequest($request->integer('request_id'), $request->integer('supplier_id'), $data, $request->only(['po_date', 'remarks']) + ['created_by' => auth()->id()]);
            SystemLog::record('CREATE', 'Manufacturing Material Purchase Order', 'Membuat Material PO: '.$po->po_number);

            return redirect()->route('mfg.material-orders.index')->with('success', 'Material PO '.$po->po_number.' dibuat sebagai DRAFT.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function orderSubmit(int $id)
    {
        return $this->transition(fn () => $this->service->submitOrder($id, auth()->id()), 'SUBMIT', 'Material PO disubmit.');
    }

    public function orderApprove(int $id)
    {
        return $this->transition(fn () => $this->service->approveOrder($id, auth()->id()), 'APPROVE', 'Material PO disetujui.');
    }

    private function masters(): array
    {
        return ['yarns' => Yarn::where('is_active', true)->orderBy('yarn_code')->get(), 'fabrics' => Fabric::where('is_active', true)->orderBy('fabric_code')->get(), 'auxiliaryMaterials' => AuxiliaryMaterial::where('is_active', true)->orderBy('material_code')->get(), 'suppliers' => Supplier::where('is_active', true)->where('supplier_type', 'RAW_MATERIAL')->orderBy('supplier_name')->get()];
    }

    private function validateItems(Request $request, bool $withPrice): array
    {
        $rules = ['items' => 'required|array|min:1', 'items.*.item_type' => 'required|in:YARN,FABRIC,AUXILIARY', 'items.*.yarn_id' => 'nullable|exists:mfg_yarns,id', 'items.*.fabric_id' => 'nullable|exists:mfg_fabrics,id', 'items.*.auxiliary_material_id' => 'nullable|exists:mfg_auxiliary_materials,id', 'items.*.item_name' => 'required|string|max:255', 'items.*.qty' => 'required|numeric|min:0.01', 'items.*.unit' => 'required|string|max:20'];
        if ($withPrice) {
            $rules['items.*.rate'] = 'required|numeric|min:0';
            $rules['items.*.source_request_detail_id'] = 'required|exists:mfg_material_purchase_request_details,id';
        }

        return $request->validate($rules)['items'];
    }

    private function transition(\Closure $action, string $event, string $message)
    {
        try {
            $document = $action();
            SystemLog::record($event, 'Manufacturing Material Procurement', $message.' '.$document->getKey());

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
