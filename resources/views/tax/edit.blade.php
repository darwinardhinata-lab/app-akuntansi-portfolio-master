@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('tax.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_tax') => route('tax.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<div class="container-fluid">

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.edit_tax') }}</h4>
        </div>
        <form action="{{ route('tax.update', $tax->id) }}" method="POST" class="card-body p-4">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.tax_name_required') }}</label>
                    <input type="text" name="tax_name" class="form-control fw-bold" value="{{ $tax->tax_name }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.tax_type_required') }}</label>
                    <select name="type" class="form-select" required>
                        <option value="addition" {{ $tax->type == 'addition' ? 'selected' : '' }}>{{ __('erp.addition_vat') }}</option>
                        <option value="deduction" {{ $tax->type == 'deduction' ? 'selected' : '' }}>{{ __('erp.deduction_wht') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.rate_percent_required') }}</label>
                    <input type="number" name="rate" class="form-control fw-bold text-primary" value="{{ $tax->rate }}" step="0.01" required>
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('tax.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-warning fw-bold px-4">{{ __('erp.update_tax') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
