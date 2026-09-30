@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => route('mfg.work-orders.index'), __('erp.bc_spk') => route('mfg.work-orders.index'), $workOrder->spk_number => null]" />
@endsection

@push('styles')
<style>
    @media print {
        .sidebar, .topbar, header, .bottom-nav, .no-print, .modal, button, form button { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        .card-header button { display: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex justify-content-end gap-2 mb-2 no-print">
        <a href="{{ route('warehouse.inbound', ['tab' => 'manufaktur']) }}" class="btn btn-sm btn-outline-primary fw-bold px-3">
            <i class="fa-solid fa-warehouse me-1"></i> {{ __('erp.mfg_see_in_warehouse') }}
        </a>
        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="fa-solid fa-print me-1"></i> Cetak SPK
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- ============ HEADER SPK ============ --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h3 class="fw-bold mb-1 text-dark">{{ $workOrder->spk_number }} <span class="badge {{ $workOrder->status === 'COMPLETED' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $workOrder->status }}</span></h3>
                    <p class="text-muted mb-0">{{ $workOrder->garment_name }} @if($workOrder->style_sku) — {{ $workOrder->style_sku }} @endif · Qty rencana: {{ number_format($workOrder->planned_qty) }} pcs</p>
                    <p class="mb-0 small"><i class="fa-solid fa-industry text-warning me-1"></i>Line Produksi: <b>{{ $workOrder->productionLine ? $workOrder->productionLine->line_code.' — '.$workOrder->productionLine->line_name : 'Belum ditetapkan' }}</b></p>
                </div>
                <div class="text-md-end">
                    <div class="small text-muted">{{ __('erp.total_material_cost_colon') }} <b>Rp {{ number_format($workOrder->total_material_cost, 2) }}</b></div>
                    <div class="small text-muted">{{ __('erp.total_process_cost_colon') }} <b>Rp {{ number_format($workOrder->total_process_cost, 2) }}</b></div>
                    <div class="fw-bold text-primary">Total WIP: Rp {{ number_format($workOrder->total_wip_cost, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><b><i class="fa-solid fa-list-check me-1"></i>Kebutuhan Bahan (Snapshot BOM)</b><small class="text-muted">Tidak mengurangi stok; gunakan transaksi Issue untuk pengeluaran aktual.</small></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Jenis</th><th>Kode</th><th>Material</th><th>Unit</th><th class="text-end">Qty/Unit</th><th class="text-end">Waste %</th><th class="text-end">Kebutuhan</th><th class="text-end">HPP Snapshot</th><th class="text-end">Estimasi Biaya</th></tr></thead><tbody>@forelse($workOrder->materialRequirements as $requirement)<tr><td>{{ $requirement->item_type }}</td><td>{{ $requirement->item_code }}</td><td>{{ $requirement->item_name }}</td><td>{{ $requirement->unit }}</td><td class="text-end">{{ number_format($requirement->qty_per_unit, 6) }}</td><td class="text-end">{{ number_format($requirement->waste_percent, 4) }}</td><td class="text-end">{{ number_format($requirement->qty_required, 6) }}</td><td class="text-end">Rp {{ number_format($requirement->unit_cost_snapshot, 2) }}</td><td class="text-end">Rp {{ number_format($requirement->estimated_total_cost, 2) }}</td></tr>@empty<tr><td colspan="9" class="text-center text-muted py-3">Tidak ada snapshot BOM. Pilih produk yang memiliki BOM saat membuat SPK.</td></tr>@endforelse</tbody>@if($workOrder->materialRequirements->isNotEmpty())<tfoot><tr class="fw-bold"><td colspan="8" class="text-end">Total Estimasi Bahan</td><td class="text-end">Rp {{ number_format($workOrder->materialRequirements->sum('estimated_total_cost'), 2) }}</td></tr></tfoot>@endif</table></div></div></div>

    <div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><div><b><i class="fa-solid fa-scale-balanced me-1"></i>Kebutuhan vs Pemakaian Aktual</b><small class="d-block text-muted">Pemakaian dibaca dari Issue Yarn/Fabric, Cutting, dan Issue Bahan Penolong yang telah diposting.</small></div>@if($workOrder->materialRequirements->isNotEmpty())<a href="{{ route('mfg.work-orders.preview-material-pr', $workOrder->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Preview PR Kekurangan</a>@endif</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Jenis</th><th>Kode</th><th>Material</th><th>Unit</th><th class="text-end">Kebutuhan BOM</th><th class="text-end">Aktual Dipakai</th><th class="text-end">Selisih</th><th class="text-end">Nilai Aktual*</th></tr></thead><tbody>@forelse($materialComparison as $comparison)<tr><td>{{ $comparison->requirement->item_type }}</td><td>{{ $comparison->requirement->item_code }}</td><td>{{ $comparison->requirement->item_name }}</td><td>{{ $comparison->requirement->unit }}</td><td class="text-end">{{ number_format($comparison->requirement->qty_required, 6) }}</td><td class="text-end">{{ number_format($comparison->actual_qty, 6) }}</td><td class="text-end {{ $comparison->variance_qty < 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ $comparison->variance_qty < 0 ? 'Lebih pakai '.number_format(abs($comparison->variance_qty), 6) : 'Sisa '.number_format($comparison->variance_qty, 6) }}</td><td class="text-end">Rp {{ number_format($comparison->actual_cost, 2) }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-3">Belum ada snapshot BOM untuk dibandingkan.</td></tr>@endforelse</tbody></table></div><div class="card-footer small text-muted">*Nilai aktual menggunakan HPP snapshot BOM untuk analisis rencana vs realisasi; transaksi/jurnal aktual tetap menjadi sumber pencatatan biaya.</div></div></div>

    {{-- ============ TAHAP 1: KNITTING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-1 me-1"></i> {{ __('erp.knitting_process') }}</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalKnitOrder"><i class="fa-solid fa-plus"></i> {{ __('erp.new_knit_order') }}</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>{{ __('erp.ko_no') }}</th><th>{{ __('erp.knitter_label') }}</th><th>{{ __('erp.target_fabric') }}</th><th>{{ __('erp.planned_kg') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th></tr></thead>
                    <tbody>
                        @forelse($workOrder->knitOrders as $ko)
                            <tr>
                                <td class="fw-bold">{{ $ko->knit_order_number }}</td>
                                <td>{{ $ko->supplier->supplier_name ?? '-' }}</td>
                                <td>{{ $ko->fabric->fabric_code ?? '-' }}</td>
                                <td class="text-end">{{ number_format($ko->planned_qty_kg, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ $ko->status }}</span></td>
                                <td>
                                    @if($ko->status === 'OPEN')
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalIssueYarn{{ $ko->id }}">{{ __('erp.issue_yarn') }}</button>
                                    @elseif($ko->status === 'ISSUED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalReceiveGrey{{ $ko->id }}">{{ __('erp.receive_grey_fabric') }}</button>
                                        @foreach($ko->yarnIssues as $yi)
                                            <form action="{{ route('mfg.knit-orders.void-yarn-issue', $yi->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Void Yarn Issue {{ $yi->issue_number }}? Stok yarn akan dikembalikan.')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void {{ $yi->issue_number }}"><i class="fa-solid fa-rotate-left"></i></button>
                                            </form>
                                        @endforeach
                                    @else
                                        <span class="text-muted small d-block">Yarn terpakai: {{ $ko->yarnIssues->sum('total_cost') ? 'Rp '.number_format($ko->yarnIssues->sum('total_cost'),2) : '-' }}</span>
                                        @foreach($ko->greyFabricReceipts as $gfr)
                                            <form action="{{ route('mfg.knit-orders.void-grey-fabric-receipt', $gfr->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Void penerimaan kain grey {{ $gfr->receipt_number }}? Jurnal & stok akan dibalik.')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger mt-1" title="Void {{ $gfr->receipt_number }}"><i class="fa-solid fa-rotate-left"></i> {{ __('erp.void_gfr') }}</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>

                            {{-- Modal Issue Yarn --}}
                            <div class="modal fade" id="modalIssueYarn{{ $ko->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.knit-orders.issue-yarn', $ko->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Issue Yarn - {{ $ko->knit_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.issue_date') }}</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Scrap/Afal Bernilai (kg)</label><input type="number" step="0.01" name="scrap_kg" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Nilai Scrap per kg</label><input type="number" step="0.01" name="scrap_unit_value" class="form-control" value="0"><div class="form-text">Isi bila scrap/afal masih bernilai; akan dipindahkan ke Persediaan Scrap-Afal.</div></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.yarn_label') }}</label>
                                                <select name="items[0][yarn_id]" class="form-select" required>
                                                    @foreach($yarns as $y)<option value="{{ $y->id }}">{{ $y->yarn_code }} (stok: {{ number_format($y->stock_quantity,2) }})</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_issued_kg') }}</label><input type="number" step="0.01" name="items[0][qty_issued]" class="form-control" required></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
                                    </div></form>
                                </div>
                            </div>

                            {{-- Modal Receive Grey Fabric --}}
                            <div class="modal fade" id="modalReceiveGrey{{ $ko->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.knit-orders.receive-grey-fabric', $ko->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Terima Kain Grey - {{ $ko->knit_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.receive_date') }}</label><input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_received_kg') }}</label><input type="number" step="0.01" name="qty_received" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_reject_kg') }}</label><input type="number" step="0.01" name="qty_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.knitting_service_cost_rp') }}</label><input type="number" step="0.01" name="knitting_cost_amount" class="form-control" value="0" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.lot_number') }}</label><input type="text" name="lot_number" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.journal_posting') }}</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">{{ __('erp.no_knit_orders_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 2: PROCESSING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-2 me-1"></i> {{ __('erp.processing_dyeing_printing_finishing') }}</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProcessingOrder"><i class="fa-solid fa-plus"></i> {{ __('erp.new_processing_order') }}</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>{{ __('erp.prc_no') }}</th><th>{{ __('erp.processor_label') }}</th><th>{{ __('erp.type_label') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th></tr></thead>
                    <tbody>
                        @forelse($workOrder->processingOrders as $po)
                            <tr>
                                <td class="fw-bold">{{ $po->order_number }}</td>
                                <td>{{ $po->supplier->supplier_name ?? '-' }}</td>
                                <td>{{ $po->process_type }}</td>
                                <td><span class="badge bg-secondary">{{ $po->status }}</span></td>
                                <td>
                                    @if($po->status === 'OPEN')
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalIssueFabric{{ $po->id }}">{{ __('erp.issue_grey_fabric') }}</button>
                                    @elseif($po->status === 'ISSUED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalReceiveFabric{{ $po->id }}">{{ __('erp.receive_finished_fabric') }}</button>
                                        @foreach($po->fabricIssues as $fi)
                                            <form action="{{ route('mfg.processing-orders.void-fabric-issue', $fi->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Void Fabric Issue {{ $fi->issue_number }}? Stok kain grey akan dikembalikan.')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void {{ $fi->issue_number }}"><i class="fa-solid fa-rotate-left"></i></button>
                                            </form>
                                        @endforeach
                                    @elseif($po->status === 'COMPLETED')
                                        @foreach($po->fabricReceipts as $fr)
                                            <form action="{{ route('mfg.processing-orders.void-fabric-receipt', $fr->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Void penerimaan kain finished {{ $fr->receipt_number }}? Jurnal & stok akan dibalik.')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void {{ $fr->receipt_number }}"><i class="fa-solid fa-rotate-left"></i> {{ __('erp.void_fr') }}</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalIssueFabric{{ $po->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.issue-fabric', $po->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Issue Kain Grey - {{ $po->order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.issue_date') }}</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.grey_fabric_label') }}</label>
                                                <select name="fabric_id" class="form-select" required>
                                                    @foreach($fabrics->where('state','GREY') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }} (stok: {{ number_format($f->stock_quantity,2) }})</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_issued_kg') }}</label><input type="number" step="0.01" name="qty_issued" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.lot_number') }}</label><input type="text" name="lot_number" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
                                    </div></form>
                                </div>
                            </div>

                            <div class="modal fade" id="modalReceiveFabric{{ $po->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.receive-fabric', $po->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Terima Kain Finished - {{ $po->order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.receive_date') }}</label><input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.target_finished_fabric') }}</label>
                                                <select name="finished_fabric_id" class="form-select" required>
                                                    @foreach($fabrics->where('state','FINISHED') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }}</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_received_kg') }}</label><input type="number" step="0.01" name="qty_received" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qty_reject_kg') }}</label><input type="number" step="0.01" name="qty_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.shrinkage_percent') }}</label><input type="number" step="0.01" name="shrinkage_percent" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.process_service_cost_rp') }}</label><input type="number" step="0.01" name="process_cost_amount" class="form-control" value="0" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.color_shade_code') }}</label><input type="text" name="color" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.journal_posting') }}</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('erp.no_processing_orders_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 3: CUTTING (mulai WIP) ============ --}}
    <div class="card shadow-sm border-0 mb-3 border-start border-primary border-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-3 me-1"></i> {{ __('erp.cutting_label') }} <span class="badge bg-primary">{{ __('erp.wip_starting_point') }}</span></b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCuttingOrder"><i class="fa-solid fa-plus"></i> {{ __('erp.new_cutting_order') }}</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>{{ __('erp.co_no') }}</th><th>{{ __('erp.mfg_material_fabric') }}</th><th>{{ __('erp.fabric_qty_kg') }}</th><th>{{ __('erp.total_cost') }}</th><th>{{ __('erp.planned_pcs') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th></tr></thead>
                    <tbody>
                        @forelse($workOrder->cuttingOrders as $co)
                            <tr>
                                <td class="fw-bold">{{ $co->cutting_order_number }}</td>
                                <td>{{ $co->fabric->fabric_code ?? '-' }}</td>
                                <td class="text-end">{{ number_format($co->fabric_qty_issued, 2) }}</td>
                                <td class="text-end">Rp {{ number_format($co->fabric_total_cost, 2) }}</td>
                                <td class="text-end">{{ number_format($co->planned_pieces) }}</td>
                                <td><span class="badge bg-secondary">{{ $co->status }}</span></td>
                                <td>
                                    @if($co->status === 'OPEN')
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCuttingCheck{{ $co->id }}">{{ __('erp.record_qc') }}</button>
                                        <form action="{{ route('mfg.cutting-orders.void', $co->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Void Cutting Order {{ $co->cutting_order_number }}? Jurnal WIP & stok kain akan dibalik.')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="{{ __('erp.void_cutting_order') }}"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                    @elseif($co->status === 'CHECKED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalStitchingOrder{{ $co->id }}">{{ __('erp.create_stitching') }}</button>
                                        @foreach($co->checks as $chk)
                                            <form action="{{ route('mfg.cutting-orders.void-check', $chk->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm(__('erp.confirm_void_qc'))">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void QC"><i class="fa-solid fa-rotate-left"></i> {{ __('erp.void_qc') }}</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalCuttingCheck{{ $co->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.cutting-orders.check', $co->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">QC Cutting - {{ $co->cutting_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.qc_date') }}</label><input type="date" name="check_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.mfg_pieces_cut') }}</label><input type="number" name="pieces_cut" class="form-control" value="{{ $co->planned_pieces }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.mfg_pieces_ok') }}</label><input type="number" name="pieces_ok" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.mfg_pieces_rejected') }}</label><input type="number" name="pieces_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.fabric_used_kg') }}</label><input type="number" step="0.01" name="fabric_used_kg" class="form-control"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.fabric_wastage_kg') }}</label><input type="number" step="0.01" name="fabric_wastage_kg" class="form-control" value="0">
                                                <div class="form-text">Jika diisi, otomatis jadi jurnal Kerugian Wastage x HPP kain ({{ number_format($co->fabric_unit_cost,2) }}/kg).</div></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_qc') }}</button></div>
                                    </div></form>
                                </div>
                            </div>

                            <div class="modal fade" id="modalStitchingOrder{{ $co->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.stitching-orders.store') }}" method="POST">@csrf
                                    @php($activeCuttingCheck = $co->checks->whereNull('voided_at')->sortByDesc('id')->first())
                                    @php($stitchedPieces = $co->stitchingOrders->where('status', '!=', 'CANCELED')->sum('pieces_issued'))
                                    @php($availableStitchingPieces = max(0, (int) ($activeCuttingCheck->pieces_ok ?? 0) - $stitchedPieces))
                                    <input type="hidden" name="cutting_order_id" value="{{ $co->id }}">
                                    <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Stitching Order dari {{ $co->cutting_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.date') }}</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.vendor_cmt') }}</label>
                                                <select name="supplier_id" class="form-select">
                                                    <option value="">{{ __('erp.internal_ph') }}</option>
                                                    @foreach($suppliers->where('supplier_type','STITCHER') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.pieces_handed_over') }}</label><input type="number" name="pieces_issued" class="form-control" min="1" max="{{ $availableStitchingPieces }}" value="{{ $availableStitchingPieces }}" required><div class="form-text">Hasil QC layak jahit: {{ number_format($activeCuttingCheck->pieces_ok ?? 0) }} pcs; sudah diserahkan: {{ number_format($stitchedPieces) }} pcs; sisa: {{ number_format($availableStitchingPieces) }} pcs.</div></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.rate_per_pcs_rp') }}</label><input type="number" step="0.01" name="stitching_rate" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.target_completion') }}</label><input type="date" name="target_date" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.journal_posting') }}</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">{{ __('erp.no_cutting_orders_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 4: STITCHING & FINISHING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white"><b><i class="fa-solid fa-4 me-1"></i> {{ __('erp.stitching_finishing') }}</b></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>{{ __('erp.sew_no') }}</th><th>{{ __('erp.pieces_label') }}</th><th>{{ __('erp.rate_label') }}</th><th>{{ __('erp.total_cost') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.finishing_stage') }}</th><th>{{ __('erp.action') }}</th></tr></thead>
                    <tbody>
                        @forelse($workOrder->cuttingOrders->pluck('stitchingOrders')->flatten(1) as $so)
                            <tr>
                                <td class="fw-bold">{{ $so->stitching_order_number }}</td>
                                <td class="text-end">{{ number_format($so->pieces_issued) }}</td>
                                <td class="text-end">Rp {{ number_format($so->stitching_rate, 2) }}</td>
                                <td class="text-end">Rp {{ number_format($so->total_stitching_cost, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ $so->status }}</span></td>
                                <td>
                                    @foreach($so->finishingStages as $fs)
                                        <span class="badge bg-light text-dark border">{{ $fs->stage }}: {{ $fs->pieces_ok }} OK</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if(!in_array($so->status, ['COMPLETED', 'CANCELED']))
                                        <button class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFinishing{{ $so->id }}">{{ __('erp.add_finishing_stage') }}</button>
                                    @endif
                                    @if($so->status !== 'CANCELED' && $so->finishingStages->isEmpty())
                                        <form action="{{ route('mfg.stitching-orders.void', $so->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Void Stitching Order {{ $so->stitching_order_number }}? Jurnal biaya CMT akan dibalik.')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="{{ __('erp.void_stitching_order') }}"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalFinishing{{ $so->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.finishing-stages.store') }}" method="POST">@csrf
                                    <input type="hidden" name="stitching_order_id" value="{{ $so->id }}">
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Tahap Finishing - {{ $so->stitching_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">{{ __('erp.stage_label') }}</label>
                                                <select name="stage" class="form-select" required>
                                                    <option value="WASHING">{{ __('erp.stage_washing') }}</option>
                                                    <option value="IRONING">{{ __('erp.stage_ironing') }}</option>
                                                    <option value="QC">{{ __('erp.qc_label') }}</option>
                                                    <option value="PACKING">{{ __('erp.packing_final_stage') }}</option>
                                                    <option value="OTHER">{{ __('erp.other_caps') }}</option>
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.date') }}</label><input type="date" name="stage_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.pieces_in') }}</label><input type="number" name="pieces_in" class="form-control" value="{{ $so->pieces_issued }}" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.mfg_pieces_ok') }}</label><input type="number" name="pieces_ok" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.mfg_pieces_rejected') }}</label><input type="number" name="pieces_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">{{ __('erp.operator_label') }}</label><input type="text" name="operator" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">{{ __('erp.no_stitching_orders_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white d-flex justify-content-between"><b>Issue Bahan Penolong</b><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAuxiliaryIssue">Issue Material</button></div><div class="card-body p-0"><table class="table table-sm mb-0"><tbody>@forelse($workOrder->auxiliaryMaterialIssues as $issue)<tr><td>{{ $issue->issue_number }}</td><td>{{ $issue->material->material_code ?? '-' }}</td><td>{{ $issue->usage_type }}</td><td>{{ number_format($issue->qty,2) }}</td><td>Rp {{ number_format($issue->total_cost,2) }}</td><td>{{ $issue->voided_at ? 'VOIDED' : 'POSTED' }}</td></tr>@empty<tr><td class="text-center text-muted">Belum ada issue bahan penolong.</td></tr>@endforelse</tbody></table></div></div>
    {{-- ============ TAHAP 5: BARCODE & PENYELESAIAN SPK ============ --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-5 me-1"></i> {{ __('erp.barcode_label_work_order_completion') }}</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBarcode"><i class="fa-solid fa-plus"></i> {{ __('erp.print_label') }}</button>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">Total label dicetak: {{ $workOrder->barcodeLabels->count() }} ({{ $workOrder->barcodeLabels->where('is_printed', true)->count() }} sudah print)</p>

            @if($workOrder->status !== 'COMPLETED')
                <hr>
                <h6 class="fw-bold">{{ __('erp.complete_spk_btn') }}</h6>
                <form action="{{ route('mfg.work-orders.complete', $workOrder->id) }}" method="POST" class="row g-2 align-items-end"
                      onsubmit="return confirm(__('erp.confirm_complete_spk'))">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">{{ __('erp.target_product_sku') }}</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">{{ __('erp.select_product_ph2') }}</option>
                            @foreach(\App\Models\Product::orderBy('name')->get() as $p)
                                <option value="{{ $p->id }}" {{ $workOrder->product_id == $p->id ? 'selected' : '' }}>{{ $p->sku ?? $p->id }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('erp.qty_finished_pcs') }}</label>
                        @php($packingFinishedQty = $workOrder->finishingStages->where('stage', 'PACKING')->sum('pieces_ok'))
                        <input type="number" step="0.01" name="qty_finished" class="form-control" value="{{ $packingFinishedQty }}" required>
                        <div class="form-text">Harus sama dengan total PACKING OK: {{ number_format($packingFinishedQty) }} pcs.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('erp.completion_date') }}</label>
                        <input type="date" name="completion_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100 fw-bold">{{ __('erp.complete_btn') }}</button>
                    </div>
                    <div class="col-12"><small class="text-muted">HPP per pcs = Total WIP (Rp {{ number_format($workOrder->total_wip_cost,2) }}) / Qty Jadi.</small></div>
                </form>
            @else
                <div class="alert alert-success mb-0">{{ __('erp.this_work_order_already') }} <b>{{ __('erp.status_completed') }}</b>. Barang jadi sudah masuk stok & jurnal WIP -> Persediaan sudah diposting.
                    <form action="{{ route('mfg.work-orders.void-completion', $workOrder->id) }}" method="POST" class="d-inline ms-2"
                          onsubmit="return confirm('Void penyelesaian SPK ini? Jurnal WIP->Persediaan & stok barang jadi akan dibalik, status kembali ke FINISHING. Hanya bisa jika stok barang jadi belum terpakai/terjual.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-rotate-left me-1"></i>{{ __('erp.void_completion') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ============ MODALS TOP-LEVEL (create baru per tahap) ============ --}}
<div class="modal fade" id="modalAuxiliaryIssue" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('mfg.auxiliary-material-issues.store') }}">@csrf<input type="hidden" name="work_order_id" value="{{ $workOrder->id }}"><input type="hidden" name="line_id" value="{{ $workOrder->line_id }}"><div class="modal-content"><div class="modal-header"><h6>Issue Bahan Penolong</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="date" name="issue_date" class="form-control mb-2" value="{{ date('Y-m-d') }}" required><select name="auxiliary_material_id" class="form-select mb-2" required><option value="">Pilih material</option>@foreach($auxiliaryMaterials as $a)<option value="{{ $a->id }}">{{ $a->material_code }} - {{ $a->material_name }} (stok {{ number_format($a->stock_quantity,2) }})</option>@endforeach</select><select name="usage_type" class="form-select mb-2"><option value="WIP">Kapitalisasi ke WIP SPK</option><option value="EXPENSE">Beban Produksi Langsung</option></select><input type="number" step="0.01" min="0.01" name="qty" class="form-control" placeholder="Qty" required></div><div class="modal-footer"><button class="btn btn-primary">Post Issue</button></div></div></form></div></div>

<div class="modal fade" id="modalKnitOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.knit-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">{{ __('erp.new_knit_order') }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">{{ __('erp.date') }}</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.knitter_label') }}</label>
                    <select name="supplier_id" class="form-select" required>
                        @foreach($suppliers->where('supplier_type','KNITTER') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.target_grey_fabric') }}</label>
                    <select name="fabric_id" class="form-select" required>
                        @foreach($fabrics->where('state','GREY') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.planned_qty_kg') }}</label><input type="number" step="0.01" name="planned_qty_kg" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.target_completion') }}</label><input type="date" name="target_date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalProcessingOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">{{ __('erp.new_processing_order') }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">{{ __('erp.date') }}</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.processor_label') }}</label>
                    <select name="supplier_id" class="form-select" required>
                        @foreach($suppliers->where('supplier_type','PROCESSOR') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.mfg_process_type') }}</label>
                    <select name="process_type" class="form-select" required>
                        <option value="DYEING">{{ __('erp.process_dyeing') }}</option><option value="PRINTING">{{ __('erp.process_printing') }}</option><option value="FINISHING">{{ __('erp.process_finishing') }}</option>
                    </select></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.target_completion') }}</label><input type="date" name="target_date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalCuttingOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.cutting-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">{{ __('erp.new_cutting_order') }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                @if(! $workOrder->productionLine)
                    <div class="alert alert-warning small">Line Produksi belum ditetapkan pada SPK ini. Kembali ke daftar SPK dan buat SPK baru dengan Line Produksi sebelum melakukan Cutting.</div>
                @elseif(! $workOrder->productionLine->is_active)
                    <div class="alert alert-warning small">Line Produksi SPK ini sedang nonaktif. Aktifkan kembali Line Produksi sebelum melakukan Cutting.</div>
                @else
                    <div class="alert alert-info small">Line Preparation akan dicatat pada {{ $workOrder->productionLine->line_code }} — {{ $workOrder->productionLine->line_name }}.</div>
                @endif
                <div class="mb-2"><label class="form-label">{{ __('erp.date') }}</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.finished_fabric_label') }}</label>
                    <select name="fabric_id" class="form-select" required>
                        @foreach($fabrics->where('state','FINISHED') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }} (stok: {{ number_format($f->stock_quantity,2) }}, HPP: Rp {{ number_format($f->average_cost,2) }})</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.fabric_qty_used_kg') }}</label><input type="number" step="0.01" name="fabric_qty_issued" class="form-control" required>
                    <div class="form-text">Ini akan langsung mereklas nilai kain -> {{ __('erp.production_wip_journal4') }}</div></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.planned_pieces') }}</label><input type="number" name="planned_pieces" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.marker_efficiency_percent') }}</label><input type="number" step="0.01" name="marker_efficiency" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary" {{ ! $workOrder->productionLine || ! $workOrder->productionLine->is_active ? 'disabled' : '' }}>{{ __('erp.post_wip_journal') }}</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalBarcode" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.barcode-labels.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">{{ __('erp.print_barcode_label') }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">{{ __('erp.batch_number') }}</label><input type="text" name="batch_number" class="form-control" required placeholder="BATCH-01"></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.size_label') }}</label><input type="text" name="sizes[0][size]" class="form-control" required placeholder="M"></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.qty') }}</label><input type="number" name="sizes[0][qty]" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">{{ __('erp.mrp_rp') }}</label><input type="number" step="0.01" name="sizes[0][mrp]" class="form-control" required></div>
                <div class="form-text">Form ini hanya utk 1 ukuran per submit — untuk multi-ukuran, ulangi submit per ukuran.</div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.print') }}</button></div>
        </div></form>
    </div>
</div>
@endsection
