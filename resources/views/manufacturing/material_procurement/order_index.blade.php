@extends('layouts.app')
@section('content')
<div class="container-fluid px-0"><h3 class="fw-bold">Material Purchase Order</h3><p class="text-muted">PO bahan baku dari Material PR approved. Tidak membuat stok atau jurnal.</p>@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Nomor</th><th>Supplier</th><th>Tanggal</th><th>Approval</th><th>Fulfillment</th><th>Nilai</th><th>Aksi</th></tr></thead><tbody>@forelse($orders as $order)<tr>
<td><a href="{{ route('mfg.material-orders.show', $order->id) }}">{{ $order->po_number }}</a></td><td>{{ $order->supplier?->supplier_name }}</td><td>{{ $order->po_date }}</td>
<td><span class="badge text-bg-{{ match($order->approval_status) { 'APPROVED' => 'success', 'REJECTED' => 'danger', 'SUBMITTED' => 'warning', default => 'secondary' } }}">{{ $order->approval_status }}</span></td>
<td>{{ $order->fulfillment_status }}</td><td>{{ number_format($order->grand_total, 2, ',', '.') }}</td><td class="d-flex gap-1">
<a class="btn btn-sm btn-outline-secondary" href="{{ route('mfg.material-orders.show', $order->id) }}">Detail</a>
@if(\App\Support\MaterialOrderAuthorization::canSubmit(auth()->user(), $order))<form method="POST" action="{{ route('mfg.material-orders.submit', $order->id) }}">@csrf<button class="btn btn-sm btn-outline-primary">Submit</button></form>
@elseif(\App\Support\MaterialOrderAuthorization::canApprove(auth()->user(), $order))<form method="POST" action="{{ route('mfg.material-orders.approve', $order->id) }}">@csrf<button class="btn btn-sm btn-success">Approve</button></form>@endif
</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">Belum ada Material PO.</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{ $orders->links() }}</div></div></div>
@endsection