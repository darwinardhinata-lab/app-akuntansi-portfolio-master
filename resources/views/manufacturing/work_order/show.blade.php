@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Manufaktur' => route('mfg.work-orders.index'), 'SPK' => route('mfg.work-orders.index'), $workOrder->spk_number => null]" />
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
                </div>
                <div class="text-md-end">
                    <div class="small text-muted">Total Biaya Bahan: <b>Rp {{ number_format($workOrder->total_material_cost, 2) }}</b></div>
                    <div class="small text-muted">Total Biaya Proses: <b>Rp {{ number_format($workOrder->total_process_cost, 2) }}</b></div>
                    <div class="fw-bold text-primary">Total WIP: Rp {{ number_format($workOrder->total_wip_cost, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 1: KNITTING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-1 me-1"></i> Knitting (Yarn -> Kain Grey)</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalKnitOrder"><i class="fa-solid fa-plus"></i> Knit Order Baru</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>No. KO</th><th>Knitter</th><th>Target Kain</th><th>Rencana (kg)</th><th>Status</th><th>Aksi</th></tr></thead>
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
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalIssueYarn{{ $ko->id }}">Issue Yarn</button>
                                    @elseif($ko->status === 'ISSUED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalReceiveGrey{{ $ko->id }}">Terima Kain Grey</button>
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
                                                <button type="submit" class="btn btn-xs btn-outline-danger mt-1" title="Void {{ $gfr->receipt_number }}"><i class="fa-solid fa-rotate-left"></i> Void GFR</button>
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
                                            <div class="mb-2"><label class="form-label">Tanggal Issue</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Yarn</label>
                                                <select name="items[0][yarn_id]" class="form-select" required>
                                                    @foreach($yarns as $y)<option value="{{ $y->id }}">{{ $y->yarn_code }} (stok: {{ number_format($y->stock_quantity,2) }})</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">Qty Issued (kg)</label><input type="number" step="0.01" name="items[0][qty_issued]" class="form-control" required></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                                    </div></form>
                                </div>
                            </div>

                            {{-- Modal Receive Grey Fabric --}}
                            <div class="modal fade" id="modalReceiveGrey{{ $ko->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.knit-orders.receive-grey-fabric', $ko->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Terima Kain Grey - {{ $ko->knit_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tanggal Terima</label><input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Qty Diterima (kg)</label><input type="number" step="0.01" name="qty_received" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Qty Reject (kg)</label><input type="number" step="0.01" name="qty_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Biaya Jasa Knitting (Rp)</label><input type="number" step="0.01" name="knitting_cost_amount" class="form-control" value="0" required></div>
                                            <div class="mb-2"><label class="form-label">Lot Number</label><input type="text" name="lot_number" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Posting Jurnal</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada Knit Order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 2: PROCESSING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-2 me-1"></i> Processing (Dyeing/Printing/Finishing Kain)</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProcessingOrder"><i class="fa-solid fa-plus"></i> Processing Order Baru</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>No. PRC</th><th>Processor</th><th>Tipe</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($workOrder->processingOrders as $po)
                            <tr>
                                <td class="fw-bold">{{ $po->order_number }}</td>
                                <td>{{ $po->supplier->supplier_name ?? '-' }}</td>
                                <td>{{ $po->process_type }}</td>
                                <td><span class="badge bg-secondary">{{ $po->status }}</span></td>
                                <td>
                                    @if($po->status === 'OPEN')
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalIssueFabric{{ $po->id }}">Issue Kain Grey</button>
                                    @elseif($po->status === 'ISSUED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalReceiveFabric{{ $po->id }}">Terima Kain Finished</button>
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
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void {{ $fr->receipt_number }}"><i class="fa-solid fa-rotate-left"></i> Void FR</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalIssueFabric{{ $po->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.issue-fabric', $po->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Issue Kain Grey - {{ $po->order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tanggal Issue</label><input type="date" name="issue_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Kain Grey</label>
                                                <select name="fabric_id" class="form-select" required>
                                                    @foreach($fabrics->where('state','GREY') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }} (stok: {{ number_format($f->stock_quantity,2) }})</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">Qty Issued (kg)</label><input type="number" step="0.01" name="qty_issued" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Lot Number</label><input type="text" name="lot_number" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                                    </div></form>
                                </div>
                            </div>

                            <div class="modal fade" id="modalReceiveFabric{{ $po->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.receive-fabric', $po->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Terima Kain Finished - {{ $po->order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tanggal Terima</label><input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Kain Finished Tujuan</label>
                                                <select name="finished_fabric_id" class="form-select" required>
                                                    @foreach($fabrics->where('state','FINISHED') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }}</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">Qty Diterima (kg)</label><input type="number" step="0.01" name="qty_received" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Qty Reject (kg)</label><input type="number" step="0.01" name="qty_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Shrinkage (%)</label><input type="number" step="0.01" name="shrinkage_percent" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Biaya Jasa Proses (Rp)</label><input type="number" step="0.01" name="process_cost_amount" class="form-control" value="0" required></div>
                                            <div class="mb-2"><label class="form-label">Warna / Shade Code</label><input type="text" name="color" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Posting Jurnal</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada Processing Order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 3: CUTTING (mulai WIP) ============ --}}
    <div class="card shadow-sm border-0 mb-3 border-start border-primary border-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-3 me-1"></i> Cutting <span class="badge bg-primary">Titik Awal WIP</span></b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCuttingOrder"><i class="fa-solid fa-plus"></i> Cutting Order Baru</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>No. CO</th><th>Kain</th><th>Qty Kain (kg)</th><th>Total Biaya</th><th>Rencana Pcs</th><th>Status</th><th>Aksi</th></tr></thead>
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
                                        <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalCuttingCheck{{ $co->id }}">Catat QC</button>
                                        <form action="{{ route('mfg.cutting-orders.void', $co->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Void Cutting Order {{ $co->cutting_order_number }}? Jurnal WIP & stok kain akan dibalik.')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Void Cutting Order"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                    @elseif($co->status === 'CHECKED')
                                        <button class="btn btn-xs btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalStitchingOrder{{ $co->id }}">Buat Stitching</button>
                                        @foreach($co->checks as $chk)
                                            <form action="{{ route('mfg.cutting-orders.void-check', $chk->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Void QC Cutting Order ini? Jurnal wastage (jika ada) akan dibalik & status kembali OPEN.')">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Void QC"><i class="fa-solid fa-rotate-left"></i> Void QC</button>
                                            </form>
                                        @endforeach
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalCuttingCheck{{ $co->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.cutting-orders.check', $co->id) }}" method="POST">@csrf
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">QC Cutting - {{ $co->cutting_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tanggal QC</label><input type="date" name="check_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces Dipotong</label><input type="number" name="pieces_cut" class="form-control" value="{{ $co->planned_pieces }}" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces OK</label><input type="number" name="pieces_ok" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces Reject</label><input type="number" name="pieces_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Fabric Terpakai (kg)</label><input type="number" step="0.01" name="fabric_used_kg" class="form-control"></div>
                                            <div class="mb-2"><label class="form-label">Fabric Wastage (kg)</label><input type="number" step="0.01" name="fabric_wastage_kg" class="form-control" value="0">
                                                <div class="form-text">Jika diisi, otomatis jadi jurnal Kerugian Wastage x HPP kain ({{ number_format($co->fabric_unit_cost,2) }}/kg).</div></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan QC</button></div>
                                    </div></form>
                                </div>
                            </div>

                            <div class="modal fade" id="modalStitchingOrder{{ $co->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.stitching-orders.store') }}" method="POST">@csrf
                                    <input type="hidden" name="cutting_order_id" value="{{ $co->id }}">
                                    <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Stitching Order dari {{ $co->cutting_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Vendor CMT</label>
                                                <select name="supplier_id" class="form-select">
                                                    <option value="">-- Internal --</option>
                                                    @foreach($suppliers->where('supplier_type','STITCHER') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">Pieces Diserahkan</label><input type="number" name="pieces_issued" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Rate per Pcs (Rp)</label><input type="number" step="0.01" name="stitching_rate" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Target Selesai</label><input type="date" name="target_date" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Posting Jurnal</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada Cutting Order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 4: STITCHING & FINISHING ============ --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white"><b><i class="fa-solid fa-4 me-1"></i> Stitching & Finishing</b></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="table-light"><tr><th>No. SEW</th><th>Pieces</th><th>Rate</th><th>Total Biaya</th><th>Status</th><th>Tahap Finishing</th><th>Aksi</th></tr></thead>
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
                                    @if($so->status !== 'COMPLETED')
                                        <button class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalFinishing{{ $so->id }}">+ Tahap Finishing</button>
                                    @endif
                                    @if($so->finishingStages->isEmpty())
                                        <form action="{{ route('mfg.stitching-orders.void', $so->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Void Stitching Order {{ $so->stitching_order_number }}? Jurnal biaya CMT akan dibalik.')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Void Stitching Order"><i class="fa-solid fa-rotate-left"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>

                            <div class="modal fade" id="modalFinishing{{ $so->id }}" tabindex="-1">
                                <div class="modal-dialog"><form action="{{ route('mfg.finishing-stages.store') }}" method="POST">@csrf
                                    <input type="hidden" name="stitching_order_id" value="{{ $so->id }}">
                                    <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Tahap Finishing - {{ $so->stitching_order_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label">Tahap</label>
                                                <select name="stage" class="form-select" required>
                                                    <option value="WASHING">WASHING</option>
                                                    <option value="IRONING">IRONING</option>
                                                    <option value="QC">QC</option>
                                                    <option value="PACKING">PACKING (tahap akhir)</option>
                                                    <option value="OTHER">OTHER</option>
                                                </select></div>
                                            <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="stage_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces Masuk</label><input type="number" name="pieces_in" class="form-control" value="{{ $so->pieces_issued }}" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces OK</label><input type="number" name="pieces_ok" class="form-control" required></div>
                                            <div class="mb-2"><label class="form-label">Pieces Reject</label><input type="number" name="pieces_rejected" class="form-control" value="0"></div>
                                            <div class="mb-2"><label class="form-label">Operator</label><input type="text" name="operator" class="form-control"></div>
                                        </div>
                                        <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                                    </div></form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada Stitching Order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ TAHAP 5: BARCODE & PENYELESAIAN SPK ============ --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <b><i class="fa-solid fa-5 me-1"></i> Barcode Label & Penyelesaian SPK</b>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalBarcode"><i class="fa-solid fa-plus"></i> Cetak Label</button>
        </div>
        <div class="card-body">
            <p class="small text-muted mb-2">Total label dicetak: {{ $workOrder->barcodeLabels->count() }} ({{ $workOrder->barcodeLabels->where('is_printed', true)->count() }} sudah print)</p>

            @if($workOrder->status !== 'COMPLETED')
                <hr>
                <h6 class="fw-bold">Selesaikan SPK (WIP -> Persediaan Barang Jadi)</h6>
                <form action="{{ route('mfg.work-orders.complete', $workOrder->id) }}" method="POST" class="row g-2 align-items-end"
                      onsubmit="return confirm('Yakin selesaikan SPK ini? Jurnal WIP->Persediaan akan diposting dan tidak bisa diulang.')">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Produk Tujuan (SKU)</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk --</option>
                            @foreach(\App\Models\Product::orderBy('name')->get() as $p)
                                <option value="{{ $p->id }}" {{ $workOrder->product_id == $p->id ? 'selected' : '' }}>{{ $p->sku ?? $p->id }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Qty Jadi (pcs)</label>
                        <input type="number" step="0.01" name="qty_finished" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="completion_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100 fw-bold">Selesaikan</button>
                    </div>
                    <div class="col-12"><small class="text-muted">HPP per pcs = Total WIP (Rp {{ number_format($workOrder->total_wip_cost,2) }}) / Qty Jadi.</small></div>
                </form>
            @else
                <div class="alert alert-success mb-0">SPK ini sudah <b>COMPLETED</b>. Barang jadi sudah masuk stok & jurnal WIP -> Persediaan sudah diposting.
                    <form action="{{ route('mfg.work-orders.void-completion', $workOrder->id) }}" method="POST" class="d-inline ms-2"
                          onsubmit="return confirm('Void penyelesaian SPK ini? Jurnal WIP->Persediaan & stok barang jadi akan dibalik, status kembali ke FINISHING. Hanya bisa jika stok barang jadi belum terpakai/terjual.')">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-rotate-left me-1"></i>Void Penyelesaian</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ============ MODALS TOP-LEVEL (create baru per tahap) ============ --}}

<div class="modal fade" id="modalKnitOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.knit-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Knit Order Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">Knitter</label>
                    <select name="supplier_id" class="form-select" required>
                        @foreach($suppliers->where('supplier_type','KNITTER') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">Target Kain Grey</label>
                    <select name="fabric_id" class="form-select" required>
                        @foreach($fabrics->where('state','GREY') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">Rencana Qty (kg)</label><input type="number" step="0.01" name="planned_qty_kg" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Target Selesai</label><input type="date" name="target_date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalProcessingOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.processing-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Processing Order Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">Processor</label>
                    <select name="supplier_id" class="form-select" required>
                        @foreach($suppliers->where('supplier_type','PROCESSOR') as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">Tipe Proses</label>
                    <select name="process_type" class="form-select" required>
                        <option value="DYEING">DYEING</option><option value="PRINTING">PRINTING</option><option value="FINISHING">FINISHING</option>
                    </select></div>
                <div class="mb-2"><label class="form-label">Target Selesai</label><input type="date" name="target_date" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalCuttingOrder" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.cutting-orders.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Cutting Order Baru</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Tanggal</label><input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="mb-2"><label class="form-label">Kain Finished</label>
                    <select name="fabric_id" class="form-select" required>
                        @foreach($fabrics->where('state','FINISHED') as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }} (stok: {{ number_format($f->stock_quantity,2) }}, HPP: Rp {{ number_format($f->average_cost,2) }})</option>@endforeach
                    </select></div>
                <div class="mb-2"><label class="form-label">Qty Kain Dipakai (kg)</label><input type="number" step="0.01" name="fabric_qty_issued" class="form-control" required>
                    <div class="form-text">Ini akan langsung mereklas nilai kain -> WIP Produksi (Jurnal #4).</div></div>
                <div class="mb-2"><label class="form-label">Rencana Pieces</label><input type="number" name="planned_pieces" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Marker Efficiency (%)</label><input type="number" step="0.01" name="marker_efficiency" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Posting Jurnal WIP</button></div>
        </div></form>
    </div>
</div>

<div class="modal fade" id="modalBarcode" tabindex="-1">
    <div class="modal-dialog"><form action="{{ route('mfg.barcode-labels.store') }}" method="POST">@csrf
        <input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">
        <div class="modal-content"><div class="modal-header"><h6 class="modal-title">Cetak Barcode Label</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Batch Number</label><input type="text" name="batch_number" class="form-control" required placeholder="BATCH-01"></div>
                <div class="mb-2"><label class="form-label">Size</label><input type="text" name="sizes[0][size]" class="form-control" required placeholder="M"></div>
                <div class="mb-2"><label class="form-label">Qty</label><input type="number" name="sizes[0][qty]" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">MRP (Rp)</label><input type="number" step="0.01" name="sizes[0][mrp]" class="form-control" required></div>
                <div class="form-text">Form ini hanya utk 1 ukuran per submit — untuk multi-ukuran, ulangi submit per ukuran.</div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Cetak</button></div>
        </div></form>
    </div>
</div>
@endsection
