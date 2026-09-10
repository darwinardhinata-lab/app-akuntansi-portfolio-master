@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('sales-returns.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_sales') => '#', __('erp.sales_return') => route('sales-returns.index'), __('erp.bc_warehouse_check') => null]" />
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    @php $canProcess = $return->status === 'PENDING_INSPECTION'; @endphp

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-success border-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ route('sales-returns.process', $return->id) }}" method="POST">
        @csrf

        {{-- Header Info Card --}}
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted fw-bold d-block">{{ __('erp.return_no') }}</small>
                        <span class="fw-bold text-danger fs-5">{{ $return->return_number }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted fw-bold d-block">{{ __('erp.return_date') }}</small>
                        <span class="fw-bold">{{ date('d M Y', strtotime($return->return_date)) }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted fw-bold d-block">{{ __('erp.ref_invoice_paren') }}</small>
                        <span class="fw-bold text-primary">{{ $return->invoice?->invoice_number ?? '-' }}</span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted fw-bold d-block">{{ __('erp.status') }}</small>
                        @php
                            $badge = match($return->status) {
                                'PENDING_INSPECTION' => 'bg-warning text-dark',
                                'APPROVED'           => 'bg-success',
                                'REJECTED'           => 'bg-danger',
                                'FAILED_DELIVERY'    => 'bg-secondary',
                                default              => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badge }} px-3 py-2">{{ str_replace('_', ' ', $return->status) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detail Barang Retur --}}
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-list me-2"></i> {{ __('erp.return_item_detail') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3 py-3">{{ __('erp.product_code') }}</th>
                            <th class="py-3">{{ __('erp.description_label') }}</th>
                            <th class="text-center py-3">{{ __('erp.qty_returned_en') }}</th>
                            <th class="text-center py-3">{{ __('erp.qty_approved') }}</th>
                            <th class="text-center py-3">{{ __('erp.condition_label') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($return->details as $detail)
                        <tr>
                            <td class="ps-3 fw-bold">{{ $detail->item_code }}</td>
                            <td>{{ $detail->description ?? '-' }}</td>
                            <td class="text-center fw-bold">{{ number_format($detail->qty_returned, 0, ',', '.') }}</td>
                            <td class="text-center">
                                <input type="number" name="items[{{ $detail->id }}][qty_approved]"
                                    class="form-control form-control-sm text-center fw-bold"
                                    value="{{ $detail->qty_approved ?: $detail->qty_returned }}"
                                    min="0" max="{{ $detail->qty_returned }}"
                                    {{ $canProcess ? '' : 'disabled' }}>
                                <input type="hidden" name="items[{{ $detail->id }}][detail_id]" value="{{ $detail->id }}">
                            </td>
                            <td class="text-center">
                                <select name="items[{{ $detail->id }}][condition]"
                                    class="form-select form-select-sm"
                                    {{ $canProcess ? '' : 'disabled' }}>
                                    <option value="GOOD" {{ ($detail->condition ?? 'GOOD') === 'GOOD' ? 'selected' : '' }}>{{ __('erp.condition_good') }}</option>
                                    <option value="DEFECTIVE" {{ ($detail->condition ?? '') === 'DEFECTIVE' ? 'selected' : '' }}>{{ __('erp.condition_defective') }}</option>
                                </select>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">{{ __('erp.no_item_detail_for_return') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Keputusan Final & Financial --}}
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-file-signature me-2"></i> {{ __('erp.final_decision') }}</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="fw-bold text-muted d-block mb-1">{{ __('erp.decision_label') }}</label>
                        <select name="decision" class="form-select fw-bold" {{ $canProcess ? '' : 'disabled' }}>
                            <option value="APPROVE" {{ ($return->final_decision ?? '') === 'APPROVE' ? 'selected' : '' }}>{{ __('erp.approve_decision') }}</option>
                            <option value="REJECT" {{ ($return->final_decision ?? '') === 'REJECT' ? 'selected' : '' }}>{{ __('erp.reject_decision') }}</option>
                            <option value="FAILED_DELIVERY" {{ ($return->final_decision ?? '') === 'FAILED_DELIVERY' ? 'selected' : '' }}>{{ __('erp.failed_delivery_decision') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted d-block mb-1">{{ __('erp.refund_shipping_cost') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">{{ __('erp.rp_symbol') }}</span>
                            <input type="number" name="refund_shipping_cost"
                                class="form-control fw-bold"
                                value="{{ old('refund_shipping_cost', $return->refund_shipping_cost ?? 0) }}"
                                min="0" step="0.01"
                                {{ $canProcess ? '' : 'disabled' }}>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="fw-bold text-muted d-block mb-1">{{ __('erp.return_shipping_cost') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">{{ __('erp.rp_symbol') }}</span>
                            <input type="number" name="return_shipping_cost"
                                class="form-control fw-bold"
                                value="{{ old('return_shipping_cost', $return->return_shipping_cost ?? 0) }}"
                                min="0" step="0.01"
                                {{ $canProcess ? '' : 'disabled' }}>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        @if($canProcess)
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-lg btn-primary fw-bold px-5 shadow-sm">
                <i class="fa-solid fa-check-double me-1"></i> Proses & Finalisasi Retur
            </button>
        </div>
        @else
        <div class="alert alert-info border-start border-info border-4" role="alert">
            <i class="fa-solid fa-lock me-2"></i>
            Retur ini sudah diproses dan tidak dapat diubah lagi.
            <a href="{{ route('sales-returns.index') }}" class="alert-link fw-bold ms-2">{{ __('erp.back_to_list') }}</a>.
        </div>
        @endif
    </form>
</div>
@endsection
