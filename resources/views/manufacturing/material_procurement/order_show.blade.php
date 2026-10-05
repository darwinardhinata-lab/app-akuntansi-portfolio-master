@extends('layouts.app')
@section('title', 'Detail Material Purchase Order')
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3"><h3 class="fw-bold">{{ $materialOrder->po_number }}</h3><a class="btn btn-outline-secondary" href="{{ route('mfg.material-orders.index') }}">Kembali</a></div>
    <p class="text-muted">Approval PO tidak membuat stok atau jurnal.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @php
        $unreceived = $materialOrder->fulfillment_status === 'OPEN'
            && !in_array($materialOrder->status, ['PARTIAL', 'RECEIVED', 'CANCELED'], true)
            && !$materialOrder->details->contains(fn ($detail) => (float) $detail->qty_received > 0);
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-3">
        @if($unreceived && \App\Support\MaterialOrderAuthorization::canEdit(auth()->user(), $materialOrder))
        <a class="btn btn-outline-secondary" href="{{ route('mfg.material-orders.edit', $materialOrder->id) }}">Edit</a>
        @endif
        @if(\App\Support\MaterialOrderAuthorization::canSubmit(auth()->user(), $materialOrder))
        <form method="POST" action="{{ route('mfg.material-orders.submit', $materialOrder->id) }}">@csrf<button class="btn btn-outline-primary">Submit</button></form>
        @endif
        @if(\App\Support\MaterialOrderAuthorization::canApprove(auth()->user(), $materialOrder))
        <form method="POST" action="{{ route('mfg.material-orders.approve', $materialOrder->id) }}">@csrf<button class="btn btn-success">Approve</button></form>
        <form method="POST" action="{{ route('mfg.material-orders.reject', $materialOrder->id) }}" class="d-flex gap-2">@csrf<label for="rejection_reason" class="visually-hidden">Alasan penolakan</label><input id="rejection_reason" class="form-control" name="rejection_reason" value="{{ old('rejection_reason') }}" required maxlength="2000" placeholder="Alasan penolakan"><button class="btn btn-danger">Reject</button></form>
        @endif
        @if($unreceived && \App\Support\MaterialOrderAuthorization::canRevise(auth()->user(), $materialOrder))
        <form method="POST" action="{{ route('mfg.material-orders.revise', $materialOrder->id) }}">@csrf<label for="revision_reason" class="form-label">Alasan revisi</label><textarea id="revision_reason" class="form-control mb-2" name="reason" required minlength="10" maxlength="1000" rows="2">{{ old('reason') }}</textarea><button class="btn btn-warning">Revise</button></form>
        @endif
    </div>
    <div class="card mb-3"><div class="card-header fw-semibold">Informasi PO</div><div class="card-body row g-3">
        <div class="col-md-3">Supplier: {{ $materialOrder->supplier?->supplier_name ?? '-' }}</div><div class="col-md-3">Tanggal: {{ $materialOrder->po_date?->format('d-m-Y') }}</div>
        <div class="col-md-3">Approval: <span class="badge text-bg-{{ match($materialOrder->approval_status) { 'APPROVED' => 'success', 'REJECTED' => 'danger', 'SUBMITTED' => 'warning', default => 'secondary' } }}">{{ $materialOrder->approval_status }}</span></div>
        <div class="col-md-3">Fulfillment: {{ $materialOrder->fulfillment_status }}</div><div class="col-md-3">Revisi: {{ $materialOrder->revision_no }}</div>
        <div class="col-md-9">Catatan: {{ $materialOrder->remarks ?? '-' }}</div>
        <div class="col-md-4">Subtotal: {{ number_format($materialOrder->sub_total, 2, ',', '.') }}</div><div class="col-md-4">Pajak: {{ number_format($materialOrder->tax_amount, 2, ',', '.') }}</div><div class="col-md-4">Total: {{ number_format($materialOrder->grand_total, 2, ',', '.') }}</div>
        @if($materialOrder->rejection_reason)<div class="col-12">Alasan penolakan: {{ $materialOrder->rejection_reason }}</div>@endif
    </div></div>
    <div class="card mb-3"><div class="card-header fw-semibold">Detail Material</div><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Tipe</th><th>Material</th><th>Detail PR Sumber</th><th>Qty</th><th>Qty Diterima</th><th>UOM</th><th>Harga</th><th>Nilai</th></tr></thead>
        <tbody>@foreach($materialOrder->details as $detail)<tr><td>{{ $detail->item_type }}</td><td>{{ $detail->item_name }}</td><td>{{ $detail->source_request_detail_id ?? '-' }}</td><td>{{ number_format($detail->qty, 2) }}</td><td>{{ number_format($detail->qty_received, 2) }}</td><td>{{ $detail->unit }}</td><td>{{ number_format($detail->rate, 2) }}</td><td>{{ number_format($detail->amount, 2) }}</td></tr>@endforeach</tbody>
    </table></div></div>
    <div class="card"><div class="card-header fw-semibold">History PO</div>
        @if($materialOrder->histories->isEmpty())<div class="card-body text-muted">PO dibuat sebelum pencatatan histori.</div>
        @else<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Waktu</th><th>Aksi</th><th>Dari → Ke</th><th>Aktor</th><th>Alasan</th><th>Revisi</th></tr></thead><tbody>
            @foreach($materialOrder->histories as $history)<tr><td>{{ $history->created_at?->format('d-m-Y H:i:s') }}</td><td>{{ $history->action }}</td><td>{{ $history->from_status ?? '-' }} → {{ $history->to_status }}</td><td>{{ $history->actor?->name ?? '-' }}</td><td style="white-space: pre-wrap">{{ $history->reason ?? '-' }}</td><td>{{ $history->revision_no }}</td></tr>@endforeach
        </tbody></table></div>@endif
    </div>
</div>
@endsection