@if(!isset($isExport) || !$isExport)
@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_balance_sheet_matrix') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">Neraca Matriks ({{ $year }})</h3>
            <p class="text-muted small mb-0">{{ __('erp.comparative_financial_position_monthly') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('neraca.index', ['tab' => 'bulanan', 'year' => $year]) }}" class="btn btn-outline-secondary fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-list me-2"></i> Tampilan Standar
            </a>
            <a href="{{ route('balance-sheet.matrix', ['year' => $year, 'export' => 'excel']) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-2"></i> {{ __('erp.export_excel_btn') }}
            </a>
        </div>
    </div>

    <form action="{{ route('balance-sheet.matrix') }}" method="GET" class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">{{ __('erp.select_year') }}</label>
                <select name="year" class="form-select form-select-sm">
                    @for($y = date('Y') - 3; $y <= date('Y'); $y++)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold w-100"><i class="fa-solid fa-filter"></i> {{ __('erp.show_label') }}</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
@endif

        <div class="table-responsive bg-white">
            <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.8rem; white-space: nowrap;">
                <thead class="table-light text-uppercase text-muted">
                    <tr>
                        <th class="ps-3">{{ __('erp.account_description') }}</th>
                        @foreach($periods as $period)
                            <th class="text-end" width="6%">{{ $period['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td class="fw-bold text-dark ps-3">{{ __('erp.assets_spaced') }}</td>
                        @foreach($periods as $period)
                            <td class="text-center" style="background-color: #f8fafc !important;"></td>
                        @endforeach
                    </tr>
                    @foreach($report['aset']['items'] as $item)
                    <tr>
                        <td class="ps-4">{{ $item['name'] }} <span class="text-muted small">[{{ $item['code'] }}]</span></td>
                        @foreach($periods as $period)
                            @php($value = $item['periods'][$period['key']])
                            <td class="text-end {{ is_null($value) ? 'bg-light' : '' }}">{{ is_null($value) ? '-' : number_format($value, 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    @endforeach

                    <tr class="table-secondary">
                        <td class="fw-bold text-dark ps-3">{{ __('erp.liabilities_spaced') }}</td>
                        @foreach($periods as $period)
                            <td class="text-center" style="background-color: #f8fafc !important;"></td>
                        @endforeach
                    </tr>
                    @foreach($report['kewajiban']['items'] as $item)
                    <tr>
                        <td class="ps-4">{{ $item['name'] }} <span class="text-muted small">[{{ $item['code'] }}]</span></td>
                        @foreach($periods as $period)
                            @php($value = $item['periods'][$period['key']])
                            <td class="text-end {{ is_null($value) ? 'bg-light' : '' }}">{{ is_null($value) ? '-' : number_format($value, 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    @endforeach

                    <tr class="table-secondary">
                        <td class="fw-bold text-dark ps-3">{{ __('erp.equity_spaced') }}</td>
                        @foreach($periods as $period)
                            <td class="text-center" style="background-color: #f8fafc !important;"></td>
                        @endforeach
                    </tr>
                    @foreach($report['modal']['items'] as $item)
                    <tr>
                        <td class="ps-4">{{ $item['name'] }} <span class="text-muted small">[{{ $item['code'] }}]</span></td>
                        @foreach($periods as $period)
                            @php($value = $item['periods'][$period['key']])
                            <td class="text-end {{ is_null($value) ? 'bg-light' : '' }}">{{ is_null($value) ? '-' : number_format($value, 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                    
                    <tr class="table-info">
                        <td class="ps-4 fw-bold text-primary">{{ __('erp.current_profit_loss') }}</td>
                        @foreach($periods as $period)
                            @php($value = $report['laba_berjalan'][$period['key']])
                            <td class="text-end fw-bold text-primary {{ is_null($value) ? 'bg-light' : '' }}">{{ is_null($value) ? '-' : number_format($value, 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

@if(!isset($isExport) || !$isExport)
    </div>
</div>
@endsection
@endif
