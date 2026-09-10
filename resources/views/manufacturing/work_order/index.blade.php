@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.mfg_work_orders') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.mfg_work_order_title') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.mfg_flow_hint') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.work-orders.index', array_merge(request()->query(), ['export' => 'excel'])) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <a href="{{ route('mfg.work-orders.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat SPK Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari No. SPK / Nama Garmen / Style SKU" value="{{ $search }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('erp.all_status_ph') }}</option>
                        @foreach(['DRAFT','CUTTING','STITCHING','FINISHING','COMPLETED','CANCELED'] as $st)
                            <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">{{ __('erp.filter_label') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr>
                            <th>{{ __('erp.work_order_no') }}</th><th>{{ __('erp.date') }}</th><th>{{ __('erp.garment_style') }}</th><th>{{ __('erp.planned_qty') }}</th>
                            <th>{{ __('erp.material_cost') }}</th><th>{{ __('erp.process_cost') }}</th><th>{{ __('erp.total_wip') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workOrders as $wo)
                            <tr>
                                <td class="fw-bold py-2">{{ $wo->spk_number }}</td>
                                <td class="py-2">{{ \Carbon\Carbon::parse($wo->order_date)->format('d M Y') }}</td>
                                <td class="py-2">{{ $wo->garment_name }} @if($wo->style_sku)<br><small class="text-muted">{{ $wo->style_sku }}</small>@endif</td>
                                <td class="text-end py-2">{{ number_format($wo->planned_qty) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($wo->total_material_cost, 2) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($wo->total_process_cost, 2) }}</td>
                                <td class="text-end py-2 fw-bold">Rp {{ number_format($wo->total_wip_cost, 2) }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $wo->status === 'COMPLETED' ? 'bg-success' : ($wo->status === 'CANCELED' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ $wo->status }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <a href="{{ route('mfg.work-orders.show', $wo->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-5 text-muted">{{ __('erp.no_work_orders_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $workOrders->links() }}
</div>
@endsection
