@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('purchase-bills.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_purchase_bill') => route('purchase-bills.index'), __('erp.bc_bill_detail') => null]" />
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-success border-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.bill_no') }}</small>
                    <span class="fw-bold text-warning fs-5">{{ $bill->bill_number }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.date') }}</small>
                    <span class="fw-bold">{{ date('d M Y', strtotime($bill->transaction_date)) }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.supplier_label') }}</small>
                    <span class="fw-bold">{{ $bill->contact_name }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.ref_po') }}</small>
                    <span class="fw-bold text-primary">{{ $bill->purchaseOrder?->po_number ?? '-' }}</span>
                </div>
                <div class="col-md-3 mt-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.payment_status') }}</small>
                    <span class="badge {{ $bill->payment_status === 'PAID' ? 'bg-success' : 'bg-danger' }} px-3 py-2">{{ $bill->payment_status }}</span>
                </div>
                <div class="col-md-3 mt-3">
                    <small class="text-muted fw-bold d-block">{{ __('erp.total_bill') }}</small>
                    <span class="fw-bold fs-5 text-primary">Rp {{ number_format($bill->grand_total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white p-3 border-bottom">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-boxes me-2"></i> {{ __('erp.item_detail') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3 py-3">{{ __('erp.product_code') }}</th>
                        <th class="py-3">{{ __('erp.description_label') }}</th>
                        <th class="text-end py-3">{{ __('erp.price_label') }}</th>
                        <th class="text-center py-3">{{ __('erp.qty') }}</th>
                        <th class="text-end py-3">{{ __('erp.discount_label') }}</th>
                        <th class="text-end py-3">{{ __('erp.subtotal_label') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bill->details as $det)
                    <tr>
                        <td class="ps-3 fw-bold">{{ $det->item_code }}</td>
                        <td>{{ $det->description ?? '-' }}</td>
                        <td class="text-end">Rp {{ number_format($det->price, 0, ',', '.') }}</td>
                        <td class="text-center fw-bold">{{ $det->qty }}</td>
                        <td class="text-end">Rp {{ number_format($det->disc_amount, 0, ',', '.') }}</td>
                        <td class="text-end fw-bold text-primary">Rp {{ number_format($det->amount, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">{{ __('erp.no_item_detail') }}</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end pe-3">{{ __('erp.subtotal_colon') }}</td>
                        <td class="text-end">Rp {{ number_format($bill->sub_total, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end pe-3">{{ __('erp.discount_colon') }}</td>
                        <td class="text-end text-danger">- Rp {{ number_format($bill->disc_amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end pe-3">{{ __('erp.tax_colon') }}</td>
                        <td class="text-end">Rp {{ number_format($bill->tax_amount, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="text-end pe-3">{{ __('erp.shipping_cost_colon') }}</td>
                        <td class="text-end">Rp {{ number_format($bill->shipping_cost, 0, ',', '.') }}</td>
                    </tr>
                    <tr class="table-primary">
                        <td colspan="5" class="text-end pe-3 fs-5">{{ __('erp.grand_total_colon_caps') }}</td>
                        <td class="text-end fs-5 fw-bold text-primary">Rp {{ number_format($bill->grand_total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('purchase-bills.index') }}" class="btn btn-light fw-bold px-4">
            <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_to') }} {{ __('erp.list_label') }}</a>
        <form action="{{ route('purchase-bills.destroy', $bill->id) }}" method="POST" onsubmit="return confirm('Hapus tagihan ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger fw-bold px-4">
                <i class="fa-solid fa-trash me-1"></i> Hapus
            </button>
        </form>
    </div>
</div>
@endsection
