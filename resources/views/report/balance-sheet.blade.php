@if(!isset($isExport) || !$isExport)
@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Neraca' => null]" />
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
    
    .table-report .col-fixed { text-align: left; position: sticky; left: 0; z-index: 2; border-right: 2px solid #e2e8f0; background: #ffffff; max-width: 250px; overflow: hidden; text-overflow: ellipsis; }
    .table-report thead .col-fixed { background-color: #f8fafc; z-index: 3; }
    
    .category-title td { font-weight: 800; color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding-top: 20px !important; text-align: left !important; background-color: #ffffff; }
    .item-name { padding-left: 15px !important; color: #334155; font-weight: 500; }
    .item-name-error { padding-left: 15px !important; color: #dc2626; font-weight: 600; }
    .italic { font-style: italic; }
    
    .line-total td { background-color: #f8fafc; font-weight: 700; color: #0f172a; border-top: 1px solid #cbd5e1; border-bottom: 2px solid #cbd5e1; }
    .line-total .col-fixed { background-color: #f8fafc; }
    
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
        .table-container { border: none !important; overflow: visible !important; }
        .table-report .col-fixed { position: static !important; box-shadow: none !important; }
        .table-report th, .table-report td { border: 1px solid #000 !important; padding: 6px !important; color: #000 !important; }
    }
</style>

<div class="report-wrapper mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">NERACA</h3>
            <p class="text-muted small mb-0">Laporan Posisi Keuangan dengan presisi sen mutlak dan sinkronisasi penuh.</p>
        </div>
        <ul class="nav nav-pills bg-white p-1 rounded-3 border shadow-sm" role="tablist">
            <li class="nav-item me-1">
                <a class="nav-link {{ $tab == 'bulanan' ? 'active' : '' }}" href="{{ route('neraca.index', ['tab' => 'bulanan']) }}">
                    <i class="fa-solid fa-calendar-days me-1"></i> Matriks Bulanan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab == 'periode' ? 'active' : '' }}" href="{{ route('neraca.index', ['tab' => 'periode']) }}">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Per Tanggal
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
    <title>Export Neraca</title>
    <style>
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10px; }
        th, td { border: 1px solid #000; padding: 4px; }
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
            <form action="{{ route('neraca.index') }}" method="GET" class="d-flex flex-wrap gap-2 align-items-center w-100 justify-content-between">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($tab == 'bulanan')
                        <label class="fw-bold text-muted small mb-0 text-nowrap">Interval:</label>
                        <select name="interval" class="form-select form-select-sm fw-bold" style="width: 140px;" onchange="this.form.submit()">
                            @foreach(\App\Support\ReportInterval::OPTIONS as $key => $label)
                                <option value="{{ $key }}" {{ $interval == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>

                        @if($interval == 'harian')
                            <label class="fw-bold text-muted small mb-0 text-nowrap">Bulan:</label>
                            <select name="month" class="form-select form-select-sm fw-bold" style="width: 130px;">
                                @foreach(['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $mi => $mn)
                                    <option value="{{ $mi+1 }}" {{ $month == ($mi+1) ? 'selected' : '' }}>{{ $mn }}</option>
                                @endforeach
                            </select>
                        @endif

                        <label class="fw-bold text-muted small mb-0 text-nowrap">Tahun:</label>
                        <input type="number" name="year" class="form-control form-control-sm fw-bold" style="width: 100px;" value="{{ $year }}">
                    @else
                        <label class="fw-bold text-muted small mb-0 text-nowrap">Per Tanggal:</label>
                        <input type="date" name="date" class="form-control form-control-sm fw-bold" value="{{ $date }}">
                    @endif
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm"><i class="fa-solid fa-filter me-1"></i> Tampilkan</button>
                </div>

                <div class="btn-group shadow-sm">
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3" title="Cetak"><i class="fa-solid fa-print"></i></button>
                    <button type="button" onclick="exportPDF()" class="btn btn-sm btn-outline-danger fw-bold px-3" title="Export PDF"><i class="fa-solid fa-file-pdf"></i></button>
                    <button type="submit" name="export" value="excel" class="btn btn-sm btn-outline-success fw-bold px-3" title="Export Excel"><i class="fa-solid fa-file-excel"></i></button>
                </div>
            </form>
        </div>
    @endif

    <div id="print-area" class="{{ (!isset($isExport) || !$isExport) ? 'p-4' : '' }}">
        @php $company = \App\Models\CompanyProfile::first(); @endphp
        
        @if($tab == 'bulanan')
            <div style="position: relative; min-height: 80px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center;">
                @if($company && $company->logo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="position: absolute; left: 0; top: 0; height: 70px; width: auto; max-width: 200px; object-fit: contain;">
                @endif
                <div style="text-align: center; width: 100%;">
                    <h4 style="margin: 0 0 4px 0; font-weight: 800; color: #4f46e5; text-transform: uppercase;">{{ $company?->company_name ?? 'BBW' }}</h4>
                    <h5 style="margin: 0 0 4px 0; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">NERACA ({{ \App\Support\ReportInterval::OPTIONS[$interval] ?? 'Bulanan' }})</h5>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">Periode: {{ \App\Support\ReportInterval::rangeLabel($interval, $year, $month) }}</p>
                </div>
            </div>

            <div class="table-container bg-white {{ (!isset($isExport) || !$isExport) ? 'border' : '' }}">
                <table class="table table-report mb-0 align-middle" style="min-width: 1300px;" {!! (isset($isExport) && $isExport) ? 'border="1"' : '' !!}>
                    <thead>
                        <tr>
                            <th class="col-fixed ps-3" width="250px">Nama Akun</th>
                            @foreach($periods as $p)
                                <th class="text-end" width="85px">{{ $p['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                         @foreach(['aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'modal' => 'Modal'] as $key => $title)
                             <tr class="category-title">
                                 <td class="col-fixed ps-3 text-primary">{{ $title }}</td>
                                 @foreach($periods as $p)
                                     <td class="text-center" style="background-color: #f8fafc !important; position: sticky; left: 0; z-index: 1;"></td>
                                 @endforeach
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
                                        $isNull = is_null($val);
                                    @endphp
                                    <td class="num-cell {{ $isNull ? '' : ($val == 0 ? 'text-muted-zero' : ($val < 0 ? 'text-negative' : '')) }}">
                                        {{ $isNull ? '' : ($val == 0 ? '-' : number_format($val, 2, ',', '.')) }}
                                    </td> 
                                @endforeach
                            </tr>
                            @endforeach

                            @if($key == 'modal')
                            <tr>
                                <td class="col-fixed ps-3 item-name italic">Laba (Rugi) Akumulatif</td>
                                @foreach($periods as $p) 
                                    @php 
                                        $labaVal = $report['laba_berjalan'][$p['key']] ?? null; 
                                        $isNull = is_null($labaVal);
                                    @endphp
                                    <td class="num-cell {{ $isNull ? '' : ($labaVal == 0 ? 'text-muted-zero' : ($labaVal < 0 ? 'text-negative' : '')) }}">
                                        {{ $isNull ? '' : ($labaVal == 0 ? '-' : number_format($labaVal, 2, ',', '.')) }}
                                    </td> 
                                @endforeach
                            </tr>
                            @endif

                            <tr class="{{ $key == 'aset' ? 'line-grand-total' : 'line-total' }}">
                                <td class="col-fixed ps-3 text-uppercase {{ $key == 'aset' ? 'py-3 text-white' : 'text-muted small' }}">
                                    TOTAL {{ strtoupper($title) }}
                                </td>
                                @foreach($periods as $p) 
                                    @php 
                                        $totVal = $report[$key]['totals'][$p['key']] ?? null; 
                                        $isNull = is_null($totVal);
                                    @endphp
                                    <td class="num-cell {{ $key == 'aset' ? 'py-3 text-white pe-2' : '' }} fw-bold {{ $isNull ? '' : ($totVal < 0 ? ($key == 'aset' ? 'text-white' : 'text-negative text-nowrap') : ($totVal == 0 ? 'text-muted-zero' : ($key == 'aset' ? 'text-white' : 'text-dark'))) }}">
                                        {{ $isNull ? '' : ($totVal == 0 ? '-' : number_format($totVal, 2, ',', '.')) }}
                                    </td> 
                                @endforeach
                            </tr>
                        @endforeach

                        <tr class="line-grand-total">
                            <td class="col-fixed ps-3 text-uppercase py-3" style="background-color: #0f172a;">TOTAL KEWAJIBAN + MODAL</td>
                            @foreach($periods as $p)
                                @php 
                                    $kVal = $report['kewajiban']['totals'][$p['key']] ?? null;
                                    $mVal = $report['modal']['totals'][$p['key']] ?? null;
                                    $grandVal = (is_null($kVal) && is_null($mVal)) ? null : ((float)$kVal + (float)$mVal);
                                @endphp
                                <td class="num-cell py-3 fw-bold text-white pe-2">
                                    {{ is_null($grandVal) ? '' : ($grandVal == 0 ? '-' : number_format($grandVal, 2, ',', '.')) }}
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>

        @elseif($tab == 'periode')
            <div style="position: relative; min-height: 80px; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center;">
                @if($company && $company->logo)
                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo" style="position: absolute; left: 0; top: 0; height: 70px; width: auto; max-width: 200px; object-fit: contain;">
                @endif
                <div style="text-align: center; width: 100%;">
                    <h4 style="margin: 0 0 4px 0; font-weight: 800; color: #4f46e5; text-transform: uppercase;">{{ $company?->company_name ?? 'BBW' }}</h4>
                    <h5 style="margin: 0 0 4px 0; font-weight: 800; color: #0f172a; letter-spacing: 0.5px;">NERACA PERIODE</h5>
                    <p style="margin: 0; color: #64748b; font-size: 0.85rem; font-weight: 500;">Per Tanggal: <span class="text-primary fw-bold">{{ date('d M Y', strtotime($date)) }}</span></p>
                </div>
            </div>

            <div class="border rounded bg-white overflow-hidden" style="max-width: 850px; margin: 0 auto;">
                <table class="table table-report w-100 mb-0 align-middle" {!! (isset($isExport) && $isExport) ? 'border="1"' : '' !!}>
                    <tbody>
                        @foreach(['aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'modal' => 'Modal'] as $key => $title)
                            <tr class="category-title"><td colspan="2" class="ps-4 text-primary" style="text-align: left;">{{ $title }}</td></tr>
                            
                            @foreach($report[$key]['items'] as $item)
                            <tr>
                                <td class="ps-4 {{ str_contains($item['name'], 'Tidak Dikenal') ? 'item-name-error' : 'item-name' }}">
                                    <span class="text-muted font-monospace small me-2">{{ $item['code'] }}</span> 
                                    <span class="text-dark fw-medium">{{ $item['name'] }}</span>
                                </td>
                                <td class="num-cell pe-4 {{ $item['balance'] < 0 ? 'text-negative' : '' }}">
                                    Rp {{ number_format($item['balance'], 2, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                            
                            @if($key == 'modal')
                            <tr>
                                <td class="ps-4 item-name italic">Laba (Rugi) Akumulatif</td>
                                <td class="num-cell pe-4 {{ $report['laba_berjalan'] < 0 ? 'text-negative' : '' }}">
                                    Rp {{ number_format($report['laba_berjalan'], 2, ',', '.') }}
                                </td>
                            </tr>
                            @endif
                            
                            <tr class="{{ $key == 'aset' ? 'line-grand-total' : 'line-total' }}">
                                <td class="ps-4 text-uppercase {{ $key == 'aset' ? 'py-3 text-white fw-bold' : 'fw-bold text-muted small' }}">
                                    TOTAL {{ strtoupper($title) }}
                                </td>
                                <td class="num-cell pe-4 {{ $key == 'aset' ? 'py-3 text-white fs-6' : 'fw-bold text-dark' }}">
                                    Rp {{ number_format($report[$key]['total'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        
                        <tr class="line-grand-total">
                            <td class="ps-4 py-3 text-uppercase">TOTAL KEWAJIBAN + MODAL</td>
                            <td class="num-cell pe-4 py-3 fs-6 text-white">
                                Rp {{ number_format($report['kewajiban']['total'] + $report['modal']['total'], 2, ',', '.') }}
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
function exportPDF() {
    var element = document.getElementById('print-area');
    html2pdf().set({ 
        margin: 0.3, 
        filename: 'Neraca{{ $tab=="bulanan" ? "_".$interval : "" }}.pdf', 
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 }, 
        jsPDF: { unit: 'in', format: 'a4', orientation: '{{ $tab=="bulanan" ? "landscape" : "portrait" }}' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    }).from(element).save();
}
</script>
@endsection
@else 
</body>
</html> 
@endif