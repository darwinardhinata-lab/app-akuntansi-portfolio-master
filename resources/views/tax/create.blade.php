@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('tax.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_tax') => route('tax.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.add_new_tax') }}</h4>
        </div>
        <form action="{{ route('tax.store') }}" method="POST" class="card-body p-4">
            @csrf
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Kode Pajak *</label>
                    <input type="text" name="tax_code" class="form-control fw-bold @error('tax_code') is-invalid @enderror" value="{{ old('tax_code') }}" placeholder="Contoh: PPN-11" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.tax_name_required') }}</label>
                    <input type="text" name="tax_name" class="form-control fw-bold @error('tax_name') is-invalid @enderror" value="{{ old('tax_name') }}" placeholder="{{ __('erp.eg_tax_name') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.tax_type_required') }}</label>
                    <select name="tax_type" class="form-select @error('tax_type') is-invalid @enderror" required>
                        <option value="ADDITION" @selected(old('tax_type') === 'ADDITION')>{{ __('erp.addition_vat') }}</option>
                        <option value="DEDUCTION" @selected(old('tax_type') === 'DEDUCTION')>{{ __('erp.deduction_wht') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.rate_percent_required') }}</label>
                    <input type="number" name="rate" class="form-control fw-bold text-primary @error('rate') is-invalid @enderror" value="{{ old('rate', 0) }}" min="0" max="999.99" step="0.01" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Kode Akun</label>
                    <input type="text" name="account_code" class="form-control" value="{{ old('account_code') }}" maxlength="50">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold small text-muted">Keterangan</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('tax.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">{{ __('erp.save_tax') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
