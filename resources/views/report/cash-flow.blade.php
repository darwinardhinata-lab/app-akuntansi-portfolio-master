@if(!isset($isExport) || !$isExport)
@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.cash_flow') => null]" />
@endsection

@section('content')
@include('partials.report-responsive')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .report-wrapper { font-family: 'Inter', sans-serif; color: #1e293b; width: 100%; margin: 0 auto; max-width: 1350px; }
    .card-report { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 30px; border: 1px solid #e2e8f0; }
    .nav-pills .nav-link { color: #64748b; font-weight: 600; border-radius: 8px; padding: 10px 20px; transition: all 0.2s; }
    .nav-pills .nav-link.active { background-color: #3b82f6; color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
    
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid #e2e8f0; }
    .table-report { margin-bottom: 0; font-size: 0.8rem; width: 100%; }
    .table-report th { background: #f8fafc; border-bottom: 2px solid #cbd5e1; font-weight: 700; color: #475569; padding: 12px 10px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
    .table-report td { padding: 10px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    
    .table-report .col-fixed { text-align: left; position: sticky; left: 0; z-index: 2; border-right: 2px solid #e2e8f0; background: #ffffff; min-width: 350px; width: 350px; white-space: normal; word-wrap: break-word; line-height: 1.4; }
    .table-report thead .col-fixed { background-color: #f8fafc; z-index: 3; }
    
    .category-title td { font-weight: 800; color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding-top: 25px !important; text-align: left !important; background-color: #ffffff; }
    .item-name { padding-left: 20px !important; color: #334155; font-weight: 500; }
    .item-name-bold { padding-left: 20px !important; color: #0f172a; font-weight: 700; }
    .italic { font-style: italic; }
    
    .line-total td { background-color: #f8fafc; font-weight: 700; color: #0f172a; border-top: 2px solid #cbd5e1; border-bottom: 2px solid #cbd5e1; }
    .line-total .col-fixed { background-color: #f8fafc; }
    
    .line-summary td { background-color: #eff6ff; font-weight: 800; color: #1d4ed8; border-top: 1px solid #93c5fd; }
    .line-summary .col-fixed { background-color: #eff6ff; }
    
    .line-grand-total td { background-color: #0f172a !important; font-weight: 800; font-size: 0.85rem; color: #ffffff !important; border: none; }
    .line-grand-total .col-fixed { background-color: #0f172a !important; color: #ffffff !important; }
    
    .num-cell { text-align: right; font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; letter-spacing: -0.2px; }
    .text-negative { color: #dc2626; }
    .text-muted-zero { color: #cbd5e1; }

    /* PERBAIKAN ERROR 1: FIX PRINT CSS */
    @media print { 
        @page { size: landscape; margin: 10mm; }
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .sidebar, .topbar, header, .bottom-nav, .no-print { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; transform: none !important; }
        .report-wrapper { margin: 0 !important; padding: 0 !important; max-width: 100% !important; }
        .card-report { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .table-responsive { border: none !important; overflow: visible !important; }
        .table-report .col-fixed { position: static !important; border-right: none !important; width: auto !important; min-width: 0 !important; }
        .table-report th, .table-report td { border: 1px solid #000 !important; padding: 6px !important; color: #000 !important; }
    }
</style>

<div class="report-wrapper mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">{{ __('erp.cash_flow_report_title') }}</h3>
            <p class="text-muted small mb-0">Rekapitulasi aliran kas masuk dan keluar berbasis matriks interval tersinkronisasi.</p>
        </div>
        <ul class="nav nav-pills bg-white p-1 rounded-3 border shadow-sm" role="tablist">
            <li class="nav-item me-1">
                <a class="nav-link {{ $tab == 'direct' ? 'active' : '' }}" href="{{ route('arus-kas.index', ['tab' => 'direct', 'year' => $year, 'interval' => $interval, 'month' => $month]) }}">
                    <i class="fa-solid fa-arrow-down-up-across-line me-1"></i> Metode Langsung (Direct)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab == 'indirect' ? 'active' : '' }}" href="{{ route('arus-kas.index', ['tab' => 'indirect', 'year' => $year, 'interval' => $interval, 'month' => $month]) }}">
                    <i class="fa-solid fa-shuffle me-1"></i> {{ __('erp.indirect_method') }}
                </a>
            </li>
        </ul>
    </div>
    <div class="card-report p-0 border-0 shadow-sm">
@else
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('erp.export_cash_flow') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
        th, td { border: 1px solid #000; padding: 5px; }
        .text-end { text-align: right; }
        .bg-light { background: #eee; }
        .fw-bold { font-weight: bold; }
        .category-title td { font-weight: bold; text-align: left; background-color: #e2e8f0; }
        .line-grand-total td { font-weight: bold; background-color: #cbd5e1; }
        .text-negative { color: red; }
    </style>
</head>
<body>
@endif

    @if(!isset($isExport) || !$isExport)
        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center no-print flex-wrap gap-2" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <form action="{{ route('arus-kas.index') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center w-100 justify-content-between">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="fw-bold text-muted small mb-0 text-nowrap">{{ __('erp.interval_colon') }}</label>
                    <select name="interval" class="form-select form-select-sm fw-bold" style="width: 140px;" onchange="this.form.submit()">
                        @foreach(\App\Support\ReportInterval::OPTIONS as $key => $label)
                            <option value="{{ $key }}" {{ $interval == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>

                    @if($interval == 'harian')
                        <label class="fw-bold text-muted small mb-0 text-nowrap">{{ __('erp.month_colon') }}</label>
                        <select name="month" class="form-select form-select-sm fw-bold" style="width: 130px;">
                            @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $mi => $mn)
                                <option value="{{ $mi+1 }}" {{ $month == ($mi+1) ? 'selected' : '' }}>{{ $mn }}</option>
                            @endforeach
                        </select>
                    @endif

                    <label class="fw-bold text-muted small mb-0 text-nowrap">{{ __('erp.fiscal_year_colon') }}</label>
                    <input type="number" name="year" class="form-control form-control-sm fw-bold w-auto" style="width: 100px;" value="{{ $year }}">
                    <button type="submit" class="btn btn-sm btn-primary fw-bold ms-1"><i class="fa-solid fa-filter"></i> {{ __('erp.show_label') }}</button>
                </div>

                <div class="btn-group shadow-sm">
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3" title="{{ __('erp.print') }}"><i class="fa-solid fa-print"></i></button>
                    <button type="button" onclick="exportPDF()" class="btn btn-sm btn-outline-danger fw-bold px-3" title="{{ __('erp.export_pdf') }}"><i class="fa-solid fa-file-pdf"></i></button>
                    <button type="submit" name="export" value="excel" class="btn btn-sm btn-outline-success fw-bold px-3" title="{{ __('erp.export_excel') }}"><i class="fa-solid fa-file-excel"></i></button>
                </div>
            </form>
        </div>
    @endif

    <div id="print-area" class="{{ (!isset($isExport) || !$isExport) ? 'p-4' : '' }}">
        @php $company = \App\Models\CompanyProfile::first(); @endphp
        
        <div style="position: relative; min-height: 80px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center;">
            @if($company && $company->logo)
                <img src="{{ asset('storage/' . $company->logo) }}" alt="{{ __('erp.logo_alt') }}" style="position: absolute; left: 0; top: 0; height: 70px; width: auto; max-width: 200px; object-fit: contain;">
            @endif
            <div style="text-align: center; width: 100%;">
                <h4 style="margin: 0 0 4px 0; font-weight: 800; color: #4f46e5; text-transform: uppercase;">{{ $company?->company_name ?? 'BBW' }}</h4>
                <h5 style="margin: 0 0 4px 0; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">LAPORAN ARUS KAS ({{ strtoupper($tab) }} METHOD)</h5>
                <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">{{ __('erp.period_colon') }} <span class="text-primary fw-bold">{{ \App\Support\ReportInterval::rangeLabel($interval, $year, $month) }} &middot; {{ \App\Support\ReportInterval::OPTIONS[$interval] ?? 'Bulanan' }}</span></p>
            </div>
        </div>

        <div class="table-responsive bg-white {{ (!isset($isExport) || !$isExport) ? 'border' : '' }}">
            <table class="table table-report mb-0 align-middle" style="min-width: 1300px;" {!! (isset($isExport) && $isExport) ? 'border="1"' : '' !!}>
                <thead>
                    <tr>
                        <th class="col-fixed ps-3" style="width: 350px; min-width: 350px;">{{ __('erp.activity_description') }}</th>
                        @foreach($periods as $p)
                            <th class="text-end" style="width: 85px; min-width: 85px;">{{ $p['label'] }}</th>
                        @endforeach
                        <th class="text-end pe-3" style="background: #e2e8f0; color: #0f172a; width: 120px; min-width: 120px;">{{ __('erp.total_rp_caps') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if($tab == 'direct')
                        <tr class="category-title">
                            <td class="col-fixed ps-3 text-primary">{{ __('erp.cash_inflow_section') }}</td>
                            @foreach($periods as $p)<td style="background-color: #ffffff;"></td>@endforeach
                            <td style="background-color: #ffffff;"></td>
                        </tr>
                        @foreach($report['inflows']['items'] as $item)
                            <tr>
                                <td class="col-fixed ps-3 item-name">
                                    @if(!empty($item['code'])) <span class="text-muted font-monospace small me-1">[{{ $item['code'] }}]</span> @endif
                                    {{ $item['name'] }}
                                </td>
                                @foreach($periods as $p)
                                    @php $val = $item['periods'][$p['key']]; @endphp
                                    <td class="num-cell {{ $val == 0 ? 'text-muted-zero' : ($val < 0 ? 'text-negative' : '') }}">
                                        {{ $val == 0 ? '-' : number_format($val, 2, ',', '.') }}
                                    </td>
                                @endforeach
                                <td class="num-cell pe-3 fw-bold bg-light {{ $item['total'] < 0 ? 'text-negative' : '' }}">{{ number_format($item['total'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="line-total">
                            <td class="col-fixed ps-3 text-uppercase text-muted small">{{ __('erp.total_cash_receipts') }}</td>
                            @foreach($periods as $p)
                                <td class="num-cell fw-bold text-success">{{ $report['inflows']['totals'][$p['key']] == 0 ? '-' : number_format($report['inflows']['totals'][$p['key']], 2, ',', '.') }}</td>
                            @endforeach
                            <td class="num-cell pe-3 fw-bold bg-light text-success">{{ number_format($report['inflows']['grand_total'], 2, ',', '.') }}</td>
                        </tr>

                        <tr class="category-title">
                            <td class="col-fixed ps-3 text-primary mt-2">{{ __('erp.cash_outflow_section') }}</td>
                            @foreach($periods as $p)<td style="background-color: #ffffff;"></td>@endforeach
                            <td style="background-color: #ffffff;"></td>
                        </tr>
                        @foreach($report['outflows']['items'] as $item)
                            <tr>
                                <td class="col-fixed ps-3 item-name">
                                    @if(!empty($item['code'])) <span class="text-muted font-monospace small me-1">[{{ $item['code'] }}]</span> @endif
                                    {{ $item['name'] }}
                                </td>
                                @foreach($periods as $p)
                                    @php $val = $item['periods'][$p['key']]; @endphp
                                    <td class="num-cell {{ $val == 0 ? 'text-muted-zero' : ($val < 0 ? 'text-negative' : '') }}">
                                        {{ $val == 0 ? '-' : number_format($val, 2, ',', '.') }}
                                    </td>
                                @endforeach
                                <td class="num-cell pe-3 fw-bold bg-light {{ $item['total'] < 0 ? 'text-negative' : '' }}">{{ number_format($item['total'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="line-total">
                            <td class="col-fixed ps-3 text-uppercase text-muted small">{{ __('erp.total_cash_disbursements') }}</td>
                            @foreach($periods as $p)
                                <td class="num-cell fw-bold text-danger">{{ $report['outflows']['totals'][$p['key']] == 0 ? '-' : number_format($report['outflows']['totals'][$p['key']], 2, ',', '.') }}</td>
                            @endforeach
                            <td class="num-cell pe-3 fw-bold bg-light text-danger">{{ number_format($report['outflows']['grand_total'], 2, ',', '.') }}</td>
                        </tr>
                    @else
                        @foreach(['operasi' => 'I. ARUS KAS DARI AKTIVITAS OPERASI', 'investasi' => 'II. ARUS KAS DARI AKTIVITAS INVESTASI', 'pendanaan' => 'III. ARUS KAS DARI AKTIVITAS PENDANAAN'] as $secKey => $secTitle)
                            <tr class="category-title">
                                <td class="col-fixed ps-3 text-primary {{ $secKey != 'operasi' ? 'mt-2' : '' }}">{{ $secTitle }}</td>
                                @foreach($periods as $p)<td style="background-color: #ffffff;"></td>@endforeach
                                <td style="background-color: #ffffff;"></td>
                            </tr>
                            
                            @forelse($report[$secKey]['items'] as $item)
                                <tr>
                                    <td class="col-fixed ps-3 {{ !empty($item['is_bold']) ? 'item-name-bold' : 'item-name' }}">
                                        @if(!empty($item['code'])) <span class="text-muted font-monospace small me-1">[{{ $item['code'] }}]</span> @endif
                                        {{ $item['name'] }}
                                    </td>
                                    @foreach($periods as $p)
                                        @php $val = $item['periods'][$p['key']]; @endphp
                                        <td class="num-cell {{ !empty($item['is_bold']) ? 'fw-bold' : '' }} {{ $val == 0 ? 'text-muted-zero' : ($val < 0 ? 'text-negative' : '') }}">
                                            {{ $val == 0 ? '-' : number_format($val, 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                    <td class="num-cell pe-3 fw-bold bg-light {{ $item['total'] < 0 ? 'text-negative' : '' }}">{{ number_format($item['total'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="col-fixed ps-3 text-muted small italic py-2">Tidak ada pergerakan aktivitas {{ strtolower(str_replace(['I. ', 'II. ', 'III. ', 'ARUS KAS DARI AKTIVITAS '], '', $secTitle)) }} di periode ini.</td>
                                    @foreach($periods as $p)<td></td>@endforeach
                                    <td></td>
                                </tr>
                            @endforelse

                            <tr class="line-total">
                                <td class="col-fixed ps-3 text-uppercase text-muted small">KAS BERSIH DARI AKTIVITAS {{ strtoupper(str_replace(['I. ', 'II. ', 'III. ', 'ARUS KAS DARI AKTIVITAS '], '', $secTitle)) }}</td>
                                @foreach($periods as $p)
                                    <td class="num-cell fw-bold {{ $report[$secKey]['totals'][$p['key']] < 0 ? 'text-negative text-nowrap' : 'text-dark' }}">
                                        {{ $report[$secKey]['totals'][$p['key']] == 0 ? '-' : number_format($report[$secKey]['totals'][$p['key']], 2, ',', '.') }}
                                    </td>
                                @endforeach
                                <td class="num-cell pe-3 fw-bold bg-light {{ $report[$secKey]['grand_total'] < 0 ? 'text-negative' : 'text-primary' }}">{{ number_format($report[$secKey]['grand_total'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif

                    <tr class="line-summary mt-3">
                        <td class="col-fixed ps-3 text-uppercase py-3">{{ __('erp.net_increase_decrease_cash') }}</td>
                        @php $runningMovement = 0; @endphp
                        @foreach($periods as $p)
                            @php 
                                if ($tab == 'direct') {
                                    $netMonth = $report['inflows']['totals'][$p['key']] - $report['outflows']['totals'][$p['key']];
                                } else {
                                    $netMonth = $report['operasi']['totals'][$p['key']] + $report['investasi']['totals'][$p['key']] + $report['pendanaan']['totals'][$p['key']];
                                }
                                $runningMovement += $netMonth;
                            @endphp
                            <td class="num-cell py-3 {{ $netMonth < 0 ? 'text-negative text-nowrap' : '' }}">{{ $netMonth == 0 ? '-' : number_format($netMonth, 2, ',', '.') }}</td>
                        @endforeach
                        <td class="num-cell pe-3 py-3 fs-6 {{ $runningMovement < 0 ? 'text-negative' : '' }}">{{ number_format($runningMovement, 2, ',', '.') }}</td>
                    </tr>

                    <tr class="line-total bg-light">
                        <td class="col-fixed ps-3 text-uppercase text-muted small">{{ __('erp.beginning_cash_bank_balance') }}</td>
                        @php $currentAwal = $saldoAwalTahun; @endphp
                        @foreach($periods as $p)
                            <td class="num-cell fw-bold text-secondary">{{ number_format($currentAwal, 2, ',', '.') }}</td>
                            @php 
                                if ($tab == 'direct') {
                                    $netMonth = $report['inflows']['totals'][$p['key']] - $report['outflows']['totals'][$p['key']];
                                } else {
                                    $netMonth = $report['operasi']['totals'][$p['key']] + $report['investasi']['totals'][$p['key']] + $report['pendanaan']['totals'][$p['key']];
                                }
                                $currentAwal += $netMonth;
                            @endphp
                        @endforeach
                        <td class="num-cell pe-3 fw-bold text-secondary">{{ number_format($saldoAwalTahun, 2, ',', '.') }}</td>
                    </tr>

                    <tr class="line-grand-total">
                        <td class="col-fixed ps-3 text-uppercase py-3" style="background-color: #0f172a;">{{ __('erp.ending_cash_bank_balance') }}</td>
                        @php $endingBalance = $saldoAwalTahun; @endphp
                        @foreach($periods as $p)
                            @php 
                                if ($tab == 'direct') {
                                    $netMonth = $report['inflows']['totals'][$p['key']] - $report['outflows']['totals'][$p['key']];
                                } else {
                                    $netMonth = $report['operasi']['totals'][$p['key']] + $report['investasi']['totals'][$p['key']] + $report['pendanaan']['totals'][$p['key']];
                                }
                                $endingBalance += $netMonth;
                            @endphp
                            <td class="num-cell py-3 fw-bold text-white pe-2">{{ number_format($endingBalance, 2, ',', '.') }}</td>
                        @endforeach
                        <td class="num-cell pe-3 py-3 fs-6 text-warning">{{ number_format($endingBalance, 2, ',', '.') }}</td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>

@if(!isset($isExport) || !$isExport)
    </div>
</div>

<script>
function exportPDF() {
    var element = document.getElementById('print-area');
    html2pdf().set({ 
        margin: 0.3, 
        filename: 'Laporan_Arus_Kas_{{ strtoupper($tab) }}_{{ $interval }}.pdf', 
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 }, 
        jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    }).from(element).save();
}
</script>
@endsection
@else 
</body>
</html> 
@endif
