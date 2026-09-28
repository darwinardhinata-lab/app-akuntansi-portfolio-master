@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <h3>Penerimaan Barang / GRN</h3>
    <p class="text-muted">Riwayat penerimaan PO beserta Bill dan jurnal terkait.</p>
    <form method="GET" class="d-flex gap-2 mb-3">
        <input class="form-control" name="search" value="{{ $search }}" placeholder="Cari nomor GRN, PO, atau Bill">
        <button class="btn btn-primary">Cari</button>
    </form>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>Tanggal</th><th>GRN</th><th>PO</th><th>Supplier</th><th>Bill</th><th>Nilai</th><th>Status</th></tr></thead>
        <tbody>@forelse($receipts as $r)
            <tr><td>{{ $r->receipt_date }}</td><td><a href="{{ route('grn.show', $r->id) }}">{{ $r->receipt_number }}</a></td>
            <td>{{ $r->po_number }}</td><td>{{ $r->vendor_name }}</td><td>{{ $r->bill_number }}</td>
            <td>{{ number_format($r->grand_total, 2, ',', '.') }}</td><td>{{ $r->status }}</td></tr>
        @empty<tr><td colspan="7">Belum ada penerimaan GRN.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $receipts->links() }}
</div>
@endsection
