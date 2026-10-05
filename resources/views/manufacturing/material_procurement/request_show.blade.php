@extends('layouts.app')

@section('title', 'Detail Purchase Request')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h3 class="fw-bold mb-1">{{ $materialRequest->request_number }}</h3><p class="text-muted mb-0">Detail Purchase Request material produksi</p></div>
        <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print</button><a href="{{ route('mfg.material-requests.index', ['tab' => 'list']) }}" class="btn btn-outline-primary">Kembali</a></div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if(\App\Support\MaterialRequestAuthorization::canEdit(auth()->user(), $materialRequest) && !$materialRequest->details->contains(fn ($detail) => (float) $detail->qty_ordered > 0))
        <a class="btn btn-outline-secondary" href="{{ route('mfg.material-requests.edit', $materialRequest->id) }}">Edit</a>
        @endif
        @if(\App\Support\MaterialRequestAuthorization::canRevise(auth()->user(), $materialRequest) && !$materialRequest->details->contains(fn ($detail) => (float) $detail->qty_ordered > 0))
        <form method="POST" action="{{ route('mfg.material-requests.revise', $materialRequest->id) }}" class="d-flex flex-wrap gap-2">@csrf<label for="revision_reason" class="form-label">Alasan revisi</label><textarea id="revision_reason" name="reason" class="form-control" required minlength="10" maxlength="1000" rows="2">{{ old('reason') }}</textarea><button class="btn btn-warning">Revise</button></form>
        @endif
        @if(\App\Support\MaterialRequestAuthorization::canSubmit(auth()->user(), $materialRequest))
        <form method="POST" action="{{ route('mfg.material-requests.submit', $materialRequest->id) }}">@csrf<button class="btn btn-outline-primary">Submit</button></form>
        @endif
        @if(\App\Support\MaterialRequestAuthorization::canApprove(auth()->user(), $materialRequest))
        <form method="POST" action="{{ route('mfg.material-requests.approve', $materialRequest->id) }}">@csrf<button class="btn btn-success">Approve</button></form>
        <form method="POST" action="{{ route('mfg.material-requests.reject', $materialRequest->id) }}" class="d-flex gap-2">@csrf<label for="rejection_reason" class="visually-hidden">Alasan penolakan</label><input id="rejection_reason" name="rejection_reason" class="form-control" required maxlength="2000" placeholder="Alasan penolakan"><button class="btn btn-danger">Reject</button></form>
        @endif
    </div>

    <div class="card mb-3"><div class="card-header fw-semibold">Informasi PR</div><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><div class="text-muted small">Tanggal Permintaan</div><div>{{ $materialRequest->request_date?->format('d-m-Y') }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Tanggal Dibutuhkan</div><div>{{ $materialRequest->required_date?->format('d-m-Y') ?? '-' }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Requester</div><div>{{ $materialRequest->creator?->name ?? 'System' }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Status</div><div><span class="badge text-bg-primary">{{ $materialRequest->approval_status }}</span></div></div>
        <div class="col-md-3"><div class="text-muted small">Sumber</div><div>{{ $materialRequest->source_work_order_id ? 'SPK #'.$materialRequest->source_work_order_id : 'Manual' }}</div></div>
        <div class="col-md-9"><div class="text-muted small">Purpose / Catatan</div><div>{{ $materialRequest->remarks ?: '-' }}</div></div>
    </div></div></div>

    <div class="card mb-3"><div class="card-header fw-semibold">Detail Material</div><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>#</th><th>Tipe</th><th>Nama Item</th><th class="text-end">Qty Request</th><th class="text-end">Qty Ordered</th><th>UOM</th><th>Catatan</th></tr></thead>
        <tbody>@foreach($materialRequest->details as $detail)<tr><td>{{ $loop->iteration }}</td><td>{{ $detail->item_type }}</td><td>{{ $detail->item_name }}</td><td class="text-end">{{ number_format((float) $detail->qty_requested, 2) }}</td><td class="text-end">{{ number_format((float) $detail->qty_ordered, 2) }}</td><td>{{ $detail->unit }}</td><td>{{ $detail->remarks ?: '-' }}</td></tr>@endforeach</tbody>
    </table></div></div>

    <div class="card"><div class="card-header fw-semibold">Riwayat Status Tersedia</div><div class="card-body"><div class="row g-3">
        <div class="col-md-4"><div class="text-muted small">Submitted</div><div>{{ $materialRequest->submitted_at?->format('d-m-Y H:i') ?? '-' }} @if($materialRequest->submitter) oleh {{ $materialRequest->submitter->name }} @endif</div></div>
        <div class="col-md-4"><div class="text-muted small">Approved</div><div>{{ $materialRequest->approved_at?->format('d-m-Y H:i') ?? '-' }} @if($materialRequest->approver) oleh {{ $materialRequest->approver->name }} @endif</div></div>
        <div class="col-md-4"><div class="text-muted small">Rejected</div><div>{{ $materialRequest->rejected_at?->format('d-m-Y H:i') ?? '-' }} @if($materialRequest->rejector) oleh {{ $materialRequest->rejector->name }} @endif</div></div>
        @if($materialRequest->rejection_reason)<div class="col-12"><div class="text-muted small">Alasan Penolakan</div><div>{{ $materialRequest->rejection_reason }}</div></div>@endif
    </div></div></div>

    <div class="card mt-3"><div class="card-header fw-semibold">History PR</div>
        @if($materialRequest->histories->isEmpty())
        <div class="card-body text-muted">PR dibuat sebelum pencatatan histori. Riwayat ini berasal dari snapshot status existing, belum merupakan approval history append-only.</div>
        @else
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Waktu</th><th>Aksi</th><th>Dari → Ke</th><th>Aktor</th><th>Alasan</th><th>Revisi</th></tr></thead>
            <tbody>@foreach($materialRequest->histories as $history)<tr>
                <td>{{ $history->created_at?->format('d-m-Y H:i:s') }}</td><td>{{ $history->action }}</td>
                <td>{{ $history->from_status ?? '-' }} → {{ $history->to_status }}</td><td>{{ $history->actor?->name ?? '-' }}</td>
                <td style="white-space: pre-wrap">{{ $history->reason ?? '-' }}</td><td>{{ $history->revision_no }}</td>
            </tr>@endforeach</tbody>
        </table></div>
        @endif
    </div>
</div>
@endsection