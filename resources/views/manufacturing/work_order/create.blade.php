@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => route('mfg.work-orders.index'), __('erp.mfg_work_orders') => route('mfg.work-orders.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold mb-1 text-dark">{{ __('erp.create_new_work_order') }}</h3>
    <p class="text-muted small mb-4">Header Surat Perintah Kerja — akan menampung seluruh biaya WIP mulai tahap Cutting.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('mfg.work-orders.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('erp.work_order_date') }}</label>
                        <input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('erp.target_completion') }}</label>
                        <input type="date" name="target_date" class="form-control" value="{{ old('target_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('erp.style_sku_optional') }}</label>
                        <input type="text" name="style_sku" class="form-control" value="{{ old('style_sku') }}" placeholder="STY-2026-001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('erp.garment_name') }}</label>
                        <input type="text" name="garment_name" class="form-control" value="{{ old('garment_name') }}" required placeholder="Kaos Polo Lengan Pendek">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('erp.planned_qty_pcs') }}</label>
                        <input type="number" name="planned_qty" class="form-control" value="{{ old('planned_qty') }}" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('erp.target_product_finished_sku') }}</label>
                        <select name="product_id" class="form-select">
                            <option value="">{{ __('erp.select_later_on_completion') }}</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku ?? $p->id }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('erp.can_leave_blank_fill_on_complete') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('erp.notes_label') }}</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save_work_order') }}</button>
                    <a href="{{ route('mfg.work-orders.index') }}" class="btn btn-outline-secondary px-4">{{ __('erp.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
