@extends('layouts.app')

@section('title', 'Purchase Request')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Purchase Request</h3>
            <p class="text-muted mb-0">Permintaan material produksi. Tidak membuat stok atau jurnal.</p>
        </div>
        <a href="{{ route('mfg.material-requests.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Create Purchase Request</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'overview' ? 'active' : '' }}" href="{{ route('mfg.material-requests.index', ['tab' => 'overview']) }}">Overview</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'list' ? 'active' : '' }}" href="{{ route('mfg.material-requests.index', ['tab' => 'list']) }}">PR List</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('mfg.material-requests.index', ['tab' => 'mine']) }}">My PR</a></li>
    </ul>

    @if($tab === 'overview')
        <div class="row g-3 mb-3">
            @foreach([
                ['Total PR', $overview['total'], 'primary'], ['Draft', $overview['draft'], 'secondary'],
                ['Submitted', $overview['submitted'], 'warning'], ['Approved', $overview['approved'], 'success'],
                ['Rejected', $overview['rejected'], 'danger'], ['My PR', $overview['mine'], 'info'],
            ] as [$label, $value, $color])
                <div class="col-6 col-md-4 col-xl-2"><div class="card h-100 border-0 shadow-sm"><div class="card-body">
                    <div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-bold text-{{ $color }}">{{ number_format($value) }}</div>
                </div></div></div>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('mfg.material-requests.index') }}" class="card card-body mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">Search</label><input type="search" class="form-control" name="search" value="{{ $search }}" placeholder="PR No, purpose/catatan, requester"></div>
            <div class="col-md-3"><label class="form-label">Approval Status</label><select class="form-select" name="status"><option value="">Semua status</option>
                @foreach(['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach
            </select></div>
            <div class="col-md-2"><label class="form-label">Requester</label><select class="form-select" name="requester_id"><option value="">Semua requester</option>
                @foreach($requesters as $requester)<option value="{{ $requester->id }}" @selected((string) ($filters['requester_id'] ?? '') === (string) $requester->id)>{{ $requester->name }}</option>@endforeach
            </select></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass"></i></button><a class="btn btn-outline-secondary" href="{{ route('mfg.material-requests.index', ['tab' => $tab]) }}" title="Reset"><i class="fa-solid fa-rotate-left"></i></a></div>
        </div>
        <div class="form-text mt-2">Filter Factory dan Purchase Type belum ditampilkan karena master serta domain resminya belum ditetapkan.</div>
    </form>

    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nomor</th><th>Tanggal</th><th>Requester</th><th>Purpose / Catatan</th><th>Sumber</th><th>Status</th><th>Detail</th><th>Aksi</th></tr></thead>
        <tbody>@forelse($requests as $materialRequest)<tr>
            <td class="fw-semibold"><a href="{{ route('mfg.material-requests.show', $materialRequest->id) }}">{{ $materialRequest->request_number }}</a></td>
            <td>{{ $materialRequest->request_date?->format('d-m-Y') }}</td><td>{{ $materialRequest->creator?->name ?? 'System' }}</td>
            <td class="text-truncate" style="max-width: 240px" title="{{ $materialRequest->remarks }}">{{ $materialRequest->remarks ?: '-' }}</td>
            <td>{{ $materialRequest->source_work_order_id ? 'SPK #'.$materialRequest->source_work_order_id : 'Manual' }}</td>
            <td><span class="badge text-bg-{{ match($materialRequest->approval_status) { 'APPROVED' => 'success', 'REJECTED' => 'danger', 'SUBMITTED' => 'warning', default => 'secondary' } }}">{{ $materialRequest->approval_status }}</span></td>
            <td>{{ $materialRequest->details_count }}</td><td><div class="d-flex flex-wrap gap-1">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mfg.material-requests.show', $materialRequest->id) }}" title="View"><i class="fa-solid fa-eye"></i></a>
                @if($materialRequest->approval_status === 'DRAFT')<form method="POST" action="{{ route('mfg.material-requests.submit', $materialRequest->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Submit</button></form>
                @elseif($materialRequest->approval_status === 'SUBMITTED')<form method="POST" action="{{ route('mfg.material-requests.approve', $materialRequest->id) }}">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
                @elseif($materialRequest->approval_status === 'APPROVED')<a class="btn btn-sm btn-primary" href="{{ route('mfg.material-orders.create', ['request_id' => $materialRequest->id]) }}">Buat PO</a>@endif
            </div></td>
        </tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada Purchase Request yang sesuai.</td></tr>@endforelse</tbody>
    </table></div>@if($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif</div>
</div>
@endsection