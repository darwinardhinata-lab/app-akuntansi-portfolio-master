@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('helper.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.master_data') => '#', __('erp.bc_helper_code') => route('helper.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.add_new_helper_code') }}</h4>
        </div>
        <form action="{{ route('helper.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.helper_code_id') }}</label>
                    <input type="text" name="helper_code" class="form-control fw-bold" placeholder="Contoh: CUST-001" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.normal_balance_position') }}</label>
                    <select name="normal_balance" class="form-select fw-bold" required>
                        <option value="DEBET">{{ __('erp.debit_caps') }}</option>
                        <option value="KREDIT">{{ __('erp.credit_caps') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.entity_store_name') }}</label>
                    <input type="text" name="entity_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.marketing_pic_name_optional') }}</label>
                    <input type="text" name="marketing_name" class="form-control">
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('helper.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">{{ __('erp.save_helper_code') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
