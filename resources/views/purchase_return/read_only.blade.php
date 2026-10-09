@extends('layouts.app')
@section('content')
<div class="container-fluid px-4 py-3">
    <a href="{{ route('purchase-returns.index') }}" class="btn btn-light mb-3">{{ __('erp.back_to_list') }}</a>
    <div class="card shadow-sm mb-3"><div class="card-body">
        <h4>{{ __('erp.purchase_return') }}: {{ $return->return_number }}</h4>
        <p>{{ __('erp.return_date') }}: {{ $return->return_date }}</p>
        <p>{{ __('erp.ref_po') }}: {{ $return->purchaseOrder?->po_number ?? '-' }}</p>
        <p>{{ __('erp.status') }}: {{ $return->status }}</p>
        <p>{{ $return->notes ?? '-' }}</p>
    </div></div>
    <div class="table-responsive"><table class="table table-bordered">
        <thead><tr><th>{{ __('erp.product_code') }}</th><th>{{ __('erp.description_label') }}</th>
            <th>{{ __('erp.qty') }}</th><th>{{ __('erp.price_label') }}</th><th>{{ __('erp.subtotal_label') }}</th></tr></thead>
        <tbody>
        @forelse($return->details as $detail)
            <tr><td>{{ $detail->item_code }}</td><td>{{ $detail->description }}</td><td>{{ $detail->qty_returned }}</td>
                <td>{{ number_format($detail->price, 2, ',', '.') }}</td><td>{{ number_format($detail->subtotal, 2, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="5">{{ __('erp.no_item_detail') }}</td></tr>
        @endforelse
        </tbody><tfoot><tr><th colspan="4">{{ __('erp.grand_total_colon_caps') }}</th>
            <th>{{ number_format($return->total_return_amount, 2, ',', '.') }}</th></tr></tfoot>
    </table></div>
</div>
@endsection