@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <a href="{{ route('grn.index') }}">Kembali ke daftar GRN</a>
    <h3 class="mt-3">{{ $receipt->receipt_number }}</h3>
    <dl class="row">
        <dt class="col-sm-3">Tanggal / Status</dt><dd class="col-sm-9">{{ $receipt->receipt_date }} / {{ $receipt->status }}</dd>
        <dt class="col-sm-3">PO / Supplier</dt><dd class="col-sm-9"><a href="{{ route('po.index', ['search' => $po->po_number]) }}">{{ $po->po_number }}</a> / {{ $po->contact_name }}</dd>
        <dt class="col-sm-3">Bill / Nilai</dt><dd class="col-sm-9"><a href="{{ route('purchase-bills.index', ['search' => $bill?->bill_number]) }}">{{ $bill?->bill_number }}</a> / {{ number_format($bill?->grand_total ?? 0, 2, ',', '.') }}</dd>
        <dt class="col-sm-3">Jurnal</dt><dd class="col-sm-9">{{ $receipt->journal_id }}</dd>
    </dl>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>SKU</th><th>Deskripsi</th><th>Diterima</th><th>Harga</th><th>Nilai</th></tr></thead>
        <tbody>@foreach($details as $d)<tr><td>{{ $d->item_code }}</td><td>{{ $d->description }}</td>
            <td>{{ $d->qty_received }}</td><td>{{ number_format($d->unit_cost, 2, ',', '.') }}</td><td>{{ number_format($d->amount, 2, ',', '.') }}</td></tr>@endforeach</tbody>
    </table></div>
    <p class="text-muted">Penerimaan posted tidak dapat diedit atau dibatalkan melalui void legacy. Hubungi operator untuk peninjauan koreksi.</p>
</div>
@endsection
