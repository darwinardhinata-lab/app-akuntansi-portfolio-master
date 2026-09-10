@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_tax') => null]" />
@endsection

@section('content')
<div class="container-fluid">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.tax_master_vat_wht') }}</h3>
            <p class="text-muted small mb-0">Konfigurasi tarif pajak penambah (PPN) dan pemotong (PPh) untuk otomatisasi pembelian.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <a href="{{ route('tax.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success fw-bold shadow-sm">{{ session('success') }}</div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('tax.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-12 col-md-8">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_number_desc') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('erp.search_tax') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('tax.index') }}" class="btn btn-sm btn-danger fw-bold" title="{{ __('erp.reset_filter') }}"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-uppercase text-muted">
                    <tr>
                        <th class="ps-4 py-3">{{ __('erp.tax_name') }}</th>
                        <th class="text-center py-3">{{ __('erp.type_label') }}</th>
                        <th class="text-center py-3">{{ __('erp.rate_percent') }}</th>
                        <th class="text-center pe-4 py-3">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($taxes as $tax)
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">{{ $tax->tax_name }}</td>
                        <td class="text-center py-3">
                            <span class="badge {{ $tax->type == 'addition' ? 'bg-success' : 'bg-danger' }}">
                                {{ $tax->type == 'addition' ? 'PENAMBAH (PPN)' : 'PEMOTONG (PPh)' }}
                            </span>
                        </td>
                        <td class="text-center py-3 fw-bold">{{ number_format($tax->rate, 0) }}%</td>
                        <td class="text-center pe-4 py-3">
                            <a href="{{ route('tax.edit', $tax->id) }}" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-pen-to-square"></i></a>
                            <form action="{{ route('tax.destroy', $tax->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pajak ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-5 text-muted">{{ __('erp.no_tax_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
