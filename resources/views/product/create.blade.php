@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('product.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.master_data') => '#', __('erp.bc_product') => route('product.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.add_new_product_master') }}</h4>
        </div>
        <form action="{{ route('product.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.sku_item_code_label') }}</label>
                    <input type="text" name="sku" class="form-control fw-bold" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.product_name_v2') }}</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.category') }}</label>
                    <input type="text" name="category_name" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.variant_label') }}</label>
                    <input type="text" name="variation" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.selling_price_rp') }}</label>
                    <input type="number" name="sell_price" class="form-control fw-bold text-success" value="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.initial_stock') }}</label>
                    <input type="number" name="stock_quantity" class="form-control fw-bold text-primary" value="0" required>
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('product.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">{{ __('erp.save_product') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
