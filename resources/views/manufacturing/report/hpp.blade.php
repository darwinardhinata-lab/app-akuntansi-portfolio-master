@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.mfg_report_hpp') => null]" />
@endsection

@push('styles')
<style>
    @media print {
        .sidebar, .topbar, header, .bottom-nav, .no-print { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.mfg_cogs_report_title') }}</h3>
            <p class="text-muted small mb-0">Rekap SPK yang sudah COMPLETED (selesai & masuk stok barang jadi) dalam periode terpilih, berdasarkan tanggal jurnal penyelesaian.</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('mfg.reports.hpp', ['start_date' => $startDate, 'end_date' => $endDate, 'export' => 'excel']) }}" class="btn btn-success btn-sm fw-bold px-3">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
                <i class="fa-solid fa-print me-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="mb-4"></div>

    <div class="card shadow-sm border-0 mb-3 no-print">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('erp.from_date') }}</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">{{ __('erp.to_date') }}</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('erp.show_label') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.total_work_orders') }}</div>
                <div class="fs-4 fw-bold">{{ $summary->total_spk }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.material_cost') }}</div>
                <div class="fs-6 fw-bold">Rp {{ number_format($summary->total_material, 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.process_cost') }}</div>
                <div class="fs-6 fw-bold">Rp {{ number_format($summary->total_process, 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100 border-danger-subtle"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.wastage_loss') }}</div>
                <div class="fs-6 fw-bold text-danger">Rp {{ number_format($summary->total_wastage, 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.total_cogs_wip') }}</div>
                <div class="fs-6 fw-bold text-primary">Rp {{ number_format($summary->total_wip, 0) }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body text-center">
                <div class="small text-muted">{{ __('erp.total_pcs_finished') }}</div>
                <div class="fs-4 fw-bold">{{ number_format($summary->total_qty) }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr>
                            <th>{{ __('erp.work_order_no') }}</th><th>{{ __('erp.mfg_status_completed') }}</th><th>{{ __('erp.garment_style') }}</th><th>{{ __('erp.product_sku_label') }}</th>
                            <th>{{ __('erp.material_cost') }}</th><th>{{ __('erp.process_cost') }}</th><th>{{ __('erp.wastage_label') }}</th><th>{{ __('erp.total_cogs') }}</th>
                            <th>{{ __('erp.qty_finished') }}</th><th>{{ __('erp.cogs_per_pcs') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workOrders as $row)
                            <tr>
                                <td class="fw-bold py-2">
                                    <a href="{{ route('mfg.work-orders.show', $row->work_order->id) }}">{{ $row->work_order->spk_number }}</a>
                                </td>
                                <td class="py-2">{{ \Carbon\Carbon::parse($row->completed_at)->format('d M Y') }}</td>
                                <td class="py-2">{{ $row->work_order->garment_name }} @if($row->work_order->style_sku)<br><small class="text-muted">{{ $row->work_order->style_sku }}</small>@endif</td>
                                <td class="py-2">{{ $row->work_order->product->sku ?? $row->work_order->product->name ?? '-' }}</td>
                                <td class="text-end py-2">Rp {{ number_format($row->material_cost, 2) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($row->process_cost, 2) }}</td>
                                <td class="text-end py-2 {{ $row->wastage_cost > 0 ? 'text-danger fw-bold' : '' }}">Rp {{ number_format($row->wastage_cost, 2) }}</td>
                                <td class="text-end py-2 fw-bold">Rp {{ number_format($row->total_wip, 2) }}</td>
                                <td class="text-end py-2">{{ number_format($row->qty_finished) }}</td>
                                <td class="text-end py-2 fw-bold text-primary">Rp {{ number_format($row->unit_cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-5 text-muted">{{ __('erp.no_completed_work_orders_period') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if($workOrders->count() > 0)
                        <tfoot>
                            <tr class="fw-bold bg-light">
                                <td colspan="4" class="text-end">{{ __('erp.total_caps') }}</td>
                                <td class="text-end">Rp {{ number_format($summary->total_material, 2) }}</td>
                                <td class="text-end">Rp {{ number_format($summary->total_process, 2) }}</td>
                                <td class="text-end text-danger">Rp {{ number_format($summary->total_wastage, 2) }}</td>
                                <td class="text-end">Rp {{ number_format($summary->total_wip, 2) }}</td>
                                <td class="text-end">{{ number_format($summary->total_qty) }}</td>
                                <td class="text-end">-</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <p class="small text-muted">
        <i class="fa-solid fa-circle-info me-1"></i>
        "Biaya Bahan" dihitung dari nilai kain saat masuk WIP (tahap Cutting), "Biaya Proses" dari
        biaya jasa jahit (Stitching CMT), dan "Wastage" dari kerugian kain terbuang saat QC Cutting
        (sudah dikeluarkan dari HPP, dicatat sbg kerugian operasional terpisah — lihat Jurnal #4b
        di MANUFACTURING_INTEGRATION.md).
    </p>
</div>
@endsection
