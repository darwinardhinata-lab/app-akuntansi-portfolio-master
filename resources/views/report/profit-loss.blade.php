@if(!isset($isExport) || !$isExport)
@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_profit_loss_report') => null]" />
@endsection

@section('content')
@include('partials.report-responsive')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .report-wrapper { font-family: 'Inter', sans-serif; color: #1e293b; width: 100%; margin: 0 auto; max-width: 1350px; }
    .card-report { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); padding: 25px; border: 1px solid #e2e8f0; }
    .nav-pills .nav-link { color: #64748b; font-weight: 600; font-size: 0.85rem; border-radius: 8px; padding: 8px 16px; transition: all 0.2s; }
    .nav-pills .nav-link.active { background-color: #3b82f6; color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
    
    .table-container { overflow-x: auto; max-width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; }
    .table-report { margin-bottom: 0; font-size: 0.8rem; width: 100%; }
    .table-report th { text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.5px; padding: 12px 10px; vertical-align: middle; border-bottom: 2px solid #cbd5e1; background-color: #f8fafc; color: #475569; font-weight: 700; }
    .table-report td { padding: 10px 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    
    .table-report .col-fixed { text-align: left; position: sticky; left: 0; z-index: 2; border-right: 2px solid #e2e8f0; background: #ffffff; max-width: 250px; overflow: hidden; text-overflow: ellipsis; box-shadow: inset -2px 0 0 #e2e8f0; }
    .table-report thead .col-fixed { background-color: #f8fafc; z-index: 3; }
    .table-report .col-fixed-right { position: sticky; right: -1px; z-index: 2; background: #f1f5f9; box-shadow: inset 2px 0 0 #cbd5e1; }
    .table-report thead .col-fixed-right { background-color: #e2e8f0; color: #0f172a; z-index: 3; }
    
    .category-title td { font-weight: 800; color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding-top: 20px !important; text-align: left !important; background-color: #ffffff; }
    .category-title .col-fixed-right { background-color: #ffffff; }
    .item-name { padding-left: 15px !important; color: #334155; font-weight: 500; }
    .item-name-error { padding-left: 15px !important; color: #dc2626; font-weight: 600; }
    
    .line-total td { background-color: #f8fafc; font-weight: 700; color: #0f172a; border-top: 1px solid #cbd5e1; border-bottom: 2px solid #cbd5e1; }
    .line-total .col-fixed { background-color: #f8fafc; }
    .line-total .col-fixed-right { background-color: #e2e8f0; }
    
    .line-sub-total td { background-color: #eff6ff; font-weight: 800; color: #1d4ed8; border-top: 1px solid #93c5fd; border-bottom: 1px solid #93c5fd; }
    .line-sub-total .col-fixed { background-color: #eff6ff; }
    .line-sub-total .col-fixed-right { background-color: #dbeafe; box-shadow: inset 2px 0 0 #93c5fd; }
    
    .line-grand-total td { background-color: #0f172a; font-weight: 800; font-size: 0.9rem; color: #ffffff; border: none; }
    .line-grand-total .col-fixed { background-color: #0f172a; color: #ffffff; box-shadow: inset -2px 0 0 #334155; }
    .line-grand-total .col-fixed-right { background-color: #0f172a; color: #ffffff; box-shadow: inset 2px 0 0 #334155; }
    
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
        .table-container { border: none !important; overflow: visible !important; }
        .table-report .col-fixed, .table-report .col-fixed-right { position: static !important; box-shadow: none !important; }
        .table-report th, .table-report td { border: 1px solid #000 !important; padding: 6px !important; color: #000 !important; }
    }
</style>

<div class="report-wrapper mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">{{ __('erp.profit_loss_report_title') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.financial_report_precision_hint') }}</p>
        </div>
        <ul class="nav nav-pills bg-white p-1 rounded-3 border shadow-sm" role="tablist">
            <li class="nav-item me-1">
                <a class="nav-link {{ $tab == 'bulanan' ? 'active' : '' }}" href="{{ route('laba-rugi.index', ['tab' => 'bulanan']) }}">
                    <i class="fa-solid fa-calendar-days me-1"></i> Matriks Bulanan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab == 'periode' ? 'active' : '' }}" href="{{ route('laba-rugi.index', ['tab' => 'periode']) }}">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Per Periode
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
    <title>{{ __('erp.export_profit_loss') }}</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 12px; }
        th, td { border: 1px solid #000000; padding: 6px; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .bg-light { background-color: #f1f5f9; }
        .category-title td { font-weight: bold; background-color: #e2e8f0; text-align: left;}
        .line-grand-total td { font-weight: bold; background-color: #cbd5e1; }
        .text-negative { color: red; }
    </style>
</head>
<body>
@endif

    @if(!isset($isExport) || !$isExport)
        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center no-print flex-wrap gap-2" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <form action="{{ route('laba-rugi.index') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center w-100 justify-content-between">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($tab == 'bulanan')
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

                        <label class="fw-bold text-muted small mb-0 text-nowrap">{{ __('erp.year_colon') }}</label>
                        <input type="number" name="year" class="form-control form-control-sm fw-bold" style="width: 100px;" value="{{ $year }}">
                    @else
                        <label class="fw-bold text-muted small mb-0 text-nowrap">{{ __('erp.from_colon') }}</label>
                        <input type="date" name="start_date" class="form-control form-control-sm fw-bold" value="{{ $startDate }}">
                        <label class="fw-bold text-muted small mb-0 ms-1 text-nowrap">{{ __('erp.to_colon') }}</label>
                        <input type="date" name="end_date" class="form-control form-control-sm fw-bold" value="{{ $endDate }}">
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.show_label') }}</button>
                </div>

                <div class="btn-group shadow-sm">
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3" title="Cetak"><i class="fa-solid fa-print"></i></button>
                    <button type="button" onclick="exportPDF('Laba_Rugi_{{ $tab }}{{ $tab=='bulanan' ? '_'.$interval : '' }}', '{{ $tab == "bulanan" ? "landscape" : "portrait" }}')" class="btn btn-sm btn-outline-danger fw-bold px-3" title="Export PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <button type="submit" name="export" value="excel" class="btn btn-sm btn-outline-success fw-bold px-3" title="Export Excel"><i class="fa-solid fa-file-excel"></i></button>
                </div>
            </form>
        </div>
    @endif

    <div id="print-area" class="{{ (!isset($isExport) || !$isExport) ? 'p-4' : '' }}">
        @php
            $company = $company ?? \App\Models\CompanyProfile::first();
            $hasLogo = isset($company) && $company->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($company->logo);
            $revGrand = $report['pendapatan']['grand_total'] ?? 0;
        @endphp
        
        @if($tab == 'bulanan')
            <div style="position: relative; min-height: 80px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center;">
                @if($hasLogo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="position: absolute; left: 0; top: 0; height: 70px; width: auto; max-width: 200px; object-fit: contain;">
                @endif
                <div style="text-align: center; width: 100%;">
                    <h4 style="margin: 0 0 4px 0; font-weight: 800; color: #4f46e5; text-transform: uppercase;">{{ $company?->company_name ?? 'BBW' }}</h4>
                    <h5 style="margin: 0 0 4px 0; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">LAPORAN LABA RUGI MATRIKS ({{ \App\Support\ReportInterval::OPTIONS[$interval] ?? 'Bulanan' }})</h5>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">{{ __('erp.period_colon') }} <span class="text-primary fw-bold">{{ \App\Support\ReportInterval::rangeLabel($interval, $year, $month) }}</span></p>
                    @if(isset($lastSync) && $lastSync)
                        @php
                            $syncTime = \Carbon\Carbon::parse($lastSync);
                            $hoursAgo = $syncTime->diffInHours(now());
                            $syncColor = $hoursAgo <= 24 ? '#16a34a' : ($hoursAgo <= 72 ? '#d97706' : '#dc2626');
                            $syncBg    = $hoursAgo <= 24 ? '#f0fdf4' : ($hoursAgo <= 72 ? '#fffbeb' : '#fef2f2');
                        @endphp
                        <p style="margin: 6px 0 0 0; font-size: 0.75rem;">
                            <span style="background: {{ $syncBg }}; color: {{ $syncColor }}; padding: 2px 10px; border-radius: 12px; font-weight: 600;">
                                ● Data terakhir disinkronkan: {{ $syncTime->translatedFormat('d M Y H:i') }}
                                ({{ $syncTime->diffForHumans() }})
                            </span>
                        </p>
                    @endif
                </div>
            </div>

            <div class="table-container bg-white {{ (!isset($isExport) || !$isExport) ? 'border' : '' }}">
                <table class="table table-report mb-0 align-middle" style="min-width: 1500px;" {!! (isset($isExport) && $isExport) ? 'border="1"' : '' !!}>
                    <thead>
                        <tr>
                            <th class="col-fixed ps-3" width="250px">{{ __('erp.account') }}</th>
                            @foreach($periods as $p)
                                <th class="text-end" width="70px">{{ $p['label'] }}</th>
                                <th class="text-end" width="50px">%</th>
                            @endforeach
                            <th class="text-end pe-3" width="120px">{{ __('erp.total_rp_caps') }}</th>
                            <th class="col-fixed-right text-end pe-3" width="70px">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $groups = ['pendapatan', 'hpp', 'biaya', 'pendapatan_lain', 'beban_lain']; @endphp
                        
                        @foreach($groups as $key)
                            @if(count($report[$key]['items']) > 0)
                                <tr class="category-title">
                                    <td class="col-fixed ps-3 text-primary">{{ $report[$key]['title'] }}</td>
                                    @foreach($periods as $p)
                                        <td class="text-center" style="background-color: #f8fafc !important; position: sticky; left: 0; z-index: 1;"></td>
                                        <td class="text-center" style="background-color: #f8fafc !important;"></td>
                                    @endforeach
                                    <td></td>
                                    <td class="col-fixed-right"></td>
                                </tr>
                                
                                @foreach($report[$key]['items'] as $item)
                                <tr>
                                    <td class="col-fixed ps-3 {{ str_contains($item['name'], 'Tidak Dikenal') ? 'item-name-error' : 'item-name' }}">
                                        <span class="text-muted font-monospace small me-1">{{ $item['code'] }}</span>
                                        <span class="text-dark">{{ $item['name'] }}</span>
                                    </td>
                                    @foreach($periods as $p)
                                        @php 
                                            $val = $item['periods'][$p['key']]; 
                                            $revPeriod = $report['pendapatan']['totals'][$p['key']] ?? 0;
                                        @endphp
                                        <td class="num-cell {{ $val == 0 ? 'text-muted-zero' : ($val < 0 ? 'text-negative' : '') }}">
                                            {{ $val == 0 ? '-' : number_format($val, 2, ',', '.') }}
                                        </td>
                                        <td class="num-cell text-muted font-monospace small {{ $revPeriod != 0 ? '' : 'text-muted-zero' }}">
                                            {{ $revPeriod != 0 ? number_format(($val / $revPeriod) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                        </td>
                                    @endforeach
                                    <td class="num-cell pe-3 fw-bold {{ $item['total'] < 0 ? 'text-negative' : '' }}">
                                        {{ number_format($item['total'], 2, ',', '.') }}
                                    </td>
                                    <td class="col-fixed-right num-cell pe-3 text-muted font-monospace small">
                                        {{ $revGrand != 0 ? number_format(($item['total'] / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                                @endforeach

                                <tr class="line-total">
                                    <td class="col-fixed ps-3 text-uppercase text-muted small">TOTAL {{ $report[$key]['title'] }}</td>
                                    @foreach($periods as $p)
                                        @php 
                                            $tv = $report[$key]['totals'][$p['key']]; 
                                            $revPeriod = $report['pendapatan']['totals'][$p['key']] ?? 0; 
                                        @endphp
                                        <td class="num-cell {{ $tv < 0 ? 'text-negative text-nowrap' : '' }}">{{ number_format($tv, 2, ',', '.') }}</td>
                                        <td class="num-cell text-muted font-monospace small">
                                            {{ $revPeriod != 0 ? number_format(($tv / $revPeriod) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                        </td>
                                    @endforeach
                                    <td class="num-cell pe-3 fw-bold {{ $report[$key]['grand_total'] < 0 ? 'text-negative' : 'text-dark' }}">
                                        {{ number_format($report[$key]['grand_total'], 2, ',', '.') }}
                                    </td>
                                    <td class="col-fixed-right num-cell pe-3 fw-bold text-dark">
                                        {{ $revGrand != 0 ? number_format(($report[$key]['grand_total'] / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @endif

                            @if($key == 'hpp')
                                <tr class="line-sub-total">
                                    <td class="col-fixed ps-3 text-uppercase fw-bold">{{ __('erp.gross_profit_caps') }}</td>
                                    @foreach($periods as $p)
                                        @php 
                                            $revPeriod = $report['pendapatan']['totals'][$p['key']] ?? 0;
                                            $lk = $revPeriod - ($report['hpp']['totals'][$p['key']] ?? 0); 
                                        @endphp
                                        <td class="num-cell fw-bold {{ $lk < 0 ? 'text-negative text-nowrap' : '' }}">{{ number_format($lk, 2, ',', '.') }}</td>
                                        <td class="num-cell text-muted font-monospace small">
                                            {{ $revPeriod != 0 ? number_format(($lk / $revPeriod) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                        </td>
                                    @endforeach
                                    @php $grandLk = $revGrand - ($report['hpp']['grand_total'] ?? 0); @endphp
                                    <td class="num-cell pe-3 fw-bold {{ $grandLk < 0 ? 'text-negative' : '' }}">
                                        {{ number_format($grandLk, 2, ',', '.') }}
                                    </td>
                                    <td class="col-fixed-right num-cell pe-3 fw-bold text-primary">
                                        {{ $revGrand != 0 ? number_format(($grandLk / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @elseif($key == 'biaya')
                                <tr class="line-sub-total">
                                    <td class="col-fixed ps-3 text-uppercase fw-bold">{{ __('erp.operating_profit_caps') }}</td>
                                    @foreach($periods as $p)
                                        @php 
                                            $revPeriod = $report['pendapatan']['totals'][$p['key']] ?? 0;
                                            $lu = $revPeriod - ($report['hpp']['totals'][$p['key']] ?? 0) - ($report['biaya']['totals'][$p['key']] ?? 0); 
                                        @endphp
                                        <td class="num-cell fw-bold {{ $lu < 0 ? 'text-negative text-nowrap' : '' }}">{{ number_format($lu, 2, ',', '.') }}</td>
                                        <td class="num-cell text-muted font-monospace small">
                                            {{ $revPeriod != 0 ? number_format(($lu / $revPeriod) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                        </td>
                                    @endforeach
                                    @php $grandLu = $revGrand - ($report['hpp']['grand_total'] ?? 0) - ($report['biaya']['grand_total'] ?? 0); @endphp
                                    <td class="num-cell pe-3 fw-bold {{ $grandLu < 0 ? 'text-negative' : '' }}">
                                        {{ number_format($grandLu, 2, ',', '.') }}
                                    </td>
                                    <td class="col-fixed-right num-cell pe-3 fw-bold text-primary">
                                        {{ $revGrand != 0 ? number_format(($grandLu / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach

                        <tr class="line-grand-total">
                            <td class="col-fixed ps-3 text-uppercase py-3">{{ __('erp.net_profit_loss_caps') }}</td>
                            @foreach($periods as $p)
                                @php 
                                    $revPeriod = $report['pendapatan']['totals'][$p['key']] ?? 0;
                                    $bersih = ($revPeriod - ($report['hpp']['totals'][$p['key']] ?? 0) - ($report['biaya']['totals'][$p['key']] ?? 0)) + ($report['pendapatan_lain']['totals'][$p['key']] ?? 0) - ($report['beban_lain']['totals'][$p['key']] ?? 0); 
                                @endphp
                                <td class="num-cell py-3 {{ $bersih < 0 ? 'text-negative text-nowrap' : '' }}">{{ number_format($bersih, 2, ',', '.') }}</td>
                                <td class="num-cell py-3 text-white-50 font-monospace small">
                                    {{ $revPeriod != 0 ? number_format(($bersih / $revPeriod) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                </td>
                            @endforeach
                            @php $grandBersih = ($revGrand - ($report['hpp']['grand_total'] ?? 0) - ($report['biaya']['grand_total'] ?? 0)) + ($report['pendapatan_lain']['grand_total'] ?? 0) - ($report['beban_lain']['grand_total'] ?? 0); @endphp
                            <td class="num-cell pe-3 py-3 fs-6 {{ $grandBersih < 0 ? 'text-negative' : '' }}">
                                {{ number_format($grandBersih, 2, ',', '.') }}
                            </td>
                            <td class="col-fixed-right num-cell pe-3 py-3 fs-6 fw-bold text-success">
                                {{ $revGrand != 0 ? number_format(($grandBersih / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        @elseif($tab == 'periode')
            <div style="position: relative; min-height: 80px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center;">
                @if($hasLogo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="position: absolute; left: 0; top: 0; height: 70px; width: auto; max-width: 200px; object-fit: contain;">
                @endif
                <div style="text-align: center; width: 100%;">
                    <h4 style="margin: 0 0 4px 0; font-weight: 800; color: #4f46e5; text-transform: uppercase;">{{ $company?->company_name ?? 'BBW' }}</h4>
                    <h5 style="margin: 0 0 4px 0; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">{{ __('erp.period_profit_loss_report_caps') }}</h5>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">{{ __('erp.period_colon') }} <span class="text-primary fw-bold">{{ date('d M Y', strtotime($startDate)) }} s/d {{ date('d M Y', strtotime($endDate)) }}</span></p>
                    @if(isset($lastSync) && $lastSync)
                        @php
                            $syncTime = \Carbon\Carbon::parse($lastSync);
                            $hoursAgo = $syncTime->diffInHours(now());
                            $syncColor = $hoursAgo <= 24 ? '#16a34a' : ($hoursAgo <= 72 ? '#d97706' : '#dc2626');
                            $syncBg    = $hoursAgo <= 24 ? '#f0fdf4' : ($hoursAgo <= 72 ? '#fffbeb' : '#fef2f2');
                        @endphp
                        <p style="margin: 6px 0 0 0; font-size: 0.75rem;">
                            <span style="background: {{ $syncBg }}; color: {{ $syncColor }}; padding: 2px 10px; border-radius: 12px; font-weight: 600;">
                                ● Data terakhir disinkronkan: {{ $syncTime->translatedFormat('d M Y H:i') }}
                                ({{ $syncTime->diffForHumans() }})
                            </span>
                        </p>
                    @endif
                </div>
            </div>

            <div class="border rounded bg-white overflow-hidden" style="max-width: 850px; margin: 0 auto;">
                <table class="table table-report w-100 mb-0 align-middle" {!! (isset($isExport) && $isExport) ? 'border="1"' : '' !!}>
                    <tbody>
                        @php $groups = ['pendapatan', 'hpp', 'biaya', 'pendapatan_lain', 'beban_lain']; @endphp
                        
                        @foreach($groups as $key)
                            @if(count($report[$key]['items']) > 0)
                                <tr><td colspan="3" class="category-title ps-4 text-primary" style="text-align:left;">{{ $report[$key]['title'] }}</td></tr>
                                
                                @foreach($report[$key]['items'] as $item)
                                <tr>
                                    <td class="ps-4 {{ str_contains($item['name'], 'Tidak Dikenal') ? 'item-name-error' : 'item-name' }}">
                                        <span class="text-muted font-monospace small me-2">{{ $item['code'] }}</span>
                                        <span class="text-dark fw-medium">{{ $item['name'] }}</span>
                                    </td>
                                    <td class="num-cell pe-4 {{ $item['balance'] < 0 ? 'text-negative' : '' }}">Rp {{ number_format($item['balance'], 2, ',', '.') }}</td>
                                    <td class="num-cell pe-4 text-muted small font-monospace" style="width: 90px;">
                                        {{ $revGrand != 0 ? number_format(($item['balance'] / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                                @endforeach
                                
                                <tr class="line-total">
                                    <td class="ps-4 fw-bold text-muted small text-uppercase">TOTAL {{ $report[$key]['title'] }}</td>
                                    <td class="num-cell pe-4 fw-bold text-dark">Rp {{ number_format($report[$key]['grand_total'], 2, ',', '.') }}</td>
                                    <td class="num-cell pe-4 fw-bold text-dark">
                                        {{ $revGrand != 0 ? number_format(($report[$key]['grand_total'] / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @endif

                            @if($key == 'hpp')
                                <tr class="line-sub-total">
                                    <td class="ps-4 fw-bold text-uppercase">{{ __('erp.gross_profit_caps') }}</td>
                                    @php $lkPeriode = $revGrand - ($report['hpp']['grand_total'] ?? 0); @endphp
                                    <td class="num-cell pe-4 fw-bold">Rp {{ number_format($lkPeriode, 2, ',', '.') }}</td>
                                    <td class="num-cell pe-4 fw-bold text-primary">
                                        {{ $revGrand != 0 ? number_format(($lkPeriode / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @elseif($key == 'biaya')
                                <tr class="line-sub-total">
                                    <td class="ps-4 fw-bold text-uppercase">{{ __('erp.operating_profit_caps') }}</td>
                                    @php $luPeriode = $revGrand - ($report['hpp']['grand_total'] ?? 0) - ($report['biaya']['grand_total'] ?? 0); @endphp
                                    <td class="num-cell pe-4 fw-bold">Rp {{ number_format($luPeriode, 2, ',', '.') }}</td>
                                    <td class="num-cell pe-4 fw-bold text-primary">
                                        {{ $revGrand != 0 ? number_format(($luPeriode / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach

                        <tr class="line-grand-total">
                            <td class="ps-4 py-3 text-uppercase">{{ __('erp.net_profit_loss_caps') }}</td>
                            @php $bersihPer = ($revGrand - ($report['hpp']['grand_total'] ?? 0) - ($report['biaya']['grand_total'] ?? 0)) + ($report['pendapatan_lain']['grand_total'] ?? 0) - ($report['beban_lain']['grand_total'] ?? 0); @endphp
                            <td class="num-cell pe-4 py-3 fs-6 {{ $bersihPer < 0 ? 'text-negative' : '' }}">Rp {{ number_format($bersihPer, 2, ',', '.') }}</td>
                            <td class="num-cell pe-4 py-3 fs-6 fw-bold text-warning">
                                {{ $revGrand != 0 ? number_format(($bersihPer / $revGrand) * 100, 1, ',', '.') . '%' : '0,0%' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@if(!isset($isExport) || !$isExport)
    </div>
</div>

<script>
    function exportPDF(filename, orientation) {
        var element = document.getElementById('print-area');
        var opt = { margin: 0.3, filename: filename + '.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'in', format: 'a4', orientation: orientation }, pagebreak: { mode: ['avoid-all', 'css', 'legacy'] } };
        html2pdf().set(opt).from(element).save();
    }
</script>
@endsection
@else
</body>
</html>
@endif
