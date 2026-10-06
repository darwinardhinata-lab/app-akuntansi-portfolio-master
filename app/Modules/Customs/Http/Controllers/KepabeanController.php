<?php

namespace App\Modules\Customs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\KepabeanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KepabeanController extends Controller
{
    public function __construct(private KepabeanService $service) {}

    public function dashboard(Request $request)
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m']);
        return view('kepabean.dashboard', $this->service->dashboard(Carbon::parse(($data['month'] ?? now()->format('Y-m')).'-01')));
    }

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'document_type' => ['nullable', Rule::in(KepabeanService::TYPES)],
            'status' => ['nullable', Rule::in(['DRAFT', 'QUEUED', 'SUBMITTED', 'UNDER_REVIEW', 'NEED_CORRECTION', 'REJECTED', 'SPPB_ISSUED', 'NPE_ISSUED', 'VOIDED'])]]);
        $query = CustomsDocument::query()->orderByDesc('created_at')->orderByDesc('id');
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('nomor_aju', 'like', $term)->orWhere('nomor_pendaftaran', 'like', $term)
                ->orWhere('internal_number', 'like', $term)->orWhere('status', 'like', $term));
        }
        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }
        $query->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']), fn ($q) => $q->where('status', '!=', 'VOIDED'));
        return view('kepabean.index', ['documents' => $query->paginate(15)->withQueryString(), 'filters' => $filters, 'types' => KepabeanService::TYPES]);
    }

    public function create()
    {
        return view('kepabean.form', ['document' => new CustomsDocument(['currency' => 'IDR', 'exchange_rate' => 1]), 'types' => KepabeanService::TYPES]);
    }

    public function show(CustomsDocument $document)
    {
        return view('kepabean.show', ['document' => $document->load(['details', 'statusHistory'])]);
    }

    public function edit(CustomsDocument $document)
    {
        abort_unless($document->status === 'DRAFT', 409, __('kepabean.draft_only'));
        return view('kepabean.form', ['document' => $document->load('details'), 'types' => KepabeanService::TYPES]);
    }

    public function store(Request $request)
    {
        $document = $this->service->save($this->validated($request), null, $request->user()->id);
        return redirect()->route('kepabean.show', $document)->with('success', __('kepabean.saved'));
    }

    public function update(Request $request, CustomsDocument $document)
    {
        try {
            $this->service->save($this->validated($request), $document, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('kepabean.show', $document)->with('success', __('kepabean.saved'));
    }

    public function destroy(Request $request, CustomsDocument $document)
    {
        try {
            $this->service->archive($document, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('kepabean.documents')->with('success', __('kepabean.archived'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'document_type' => ['required', Rule::in(KepabeanService::TYPES)], 'nomor_aju' => 'nullable|string|max:100',
            'kode_kantor' => 'nullable|string|max:20', 'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0.000001', 'details' => 'required|array|min:1|max:500',
            'details.*' => 'required|array:hs_code,deskripsi_barang,qty,satuan,berat_bersih,nilai',
            'details.*.hs_code' => 'nullable|string|max:20', 'details.*.deskripsi_barang' => 'required|string|max:255',
            'details.*.qty' => 'required|numeric|min:0.0001', 'details.*.satuan' => 'required|string|max:20',
            'details.*.berat_bersih' => 'nullable|numeric|min:0', 'details.*.nilai' => 'required|numeric|min:0',
        ]);
    }
}