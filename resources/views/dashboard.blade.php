@extends('layouts.app')

@section('title', __('erp.dashboard'))

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.dashboard') => null]" />
@endsection

@section('content')
<div class="container-fluid pt-3 pb-4">

<style>
    /* CSS Kartu Bersih (TANPA overflow: hidden yang memotong tabel) */
    .dash-card {
        background-color: #ffffff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #f1f5f9;
        height: 100%; 
        display: flex;
        flex-direction: column;
    }
    .dash-header {
        padding: 15px 20px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .dash-header h5 { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; }
    .dash-header a { font-size: 0.85rem; font-weight: 600; text-decoration: none; color: #3b82f6; }
    
    /* Flex 1 agar tinggi sama, tapi dibiarkan terbuka agar table-responsive bekerja */
    .dash-body { padding: 0; flex: 1; }
    .dash-body-pad { padding: 20px; flex: 1; }

    /* Kotak KPI Atas */
    .kpi-box { padding: 20px; border-radius: 10px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; height: 100%; }
    .kpi-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 12px; }
    .kpi-title { font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-value { font-size: 1.6rem; font-weight: 700; color: #0f172a; margin: 5px 0; }

    /* List Payment Plan & Asset dengan internal scroll */
    .scroll-list { margin: 0; padding: 0; list-style: none; overflow-y: auto; max-height: 320px; }
    .scroll-list-item { padding: 12px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .scroll-list-item:last-child { border-bottom: none; }

    /* Tombol Aksi Cepat */
    .btn-quick { display: block; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; text-decoration: none; font-weight: 600; font-size: 0.85rem; text-align: left; transition: all 0.2s; }
    .btn-quick:hover { background: #f1f5f9; color: #2563eb; border-color: #cbd5e1; transform: translateY(-1px); }

    /* Custom Scrollbar Elegan */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

{{-- ── JUDUL HALAMAN ── --}}
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h4 class="m-0 fw-bold" style="color: #0f172a;"><i class="fas fa-gauge-high me-2 text-primary"></i> {{ __('erp.executive_dashboard') }}</h4>
        <small class="text-muted">{{ __('erp.latest_financial_summary') }} ({{ \Carbon\Carbon::now()->translatedFormat('d F Y') }})</small>
    </div>
    <div>
        <span class="badge bg-primary px-3 py-2 text-white" style="font-size: 14px; border-radius: 20px;">
            <i class="fas fa-calendar-alt me-1"></i> {{ __('erp.fiscal_year') }} {{ $year ?? date('Y') }}
        </span>
    </div>
</div>

{{-- ── 1. KPI CARDS ── --}}
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-lg-6 col-md-6 col-12">
        <div class="kpi-box">
            <div class="kpi-icon" style="background-color: #dcfce7; color: #16a34a;"><i class="fas fa-arrow-trend-up"></i></div>
            <div class="kpi-title">{{ __('erp.income') }}</div>
            <div class="kpi-value text-success">Rp {{ number_format($kpi['pemasukan'] ?? 0, 0, ',', '.') }}</div>
            <small class="text-muted">{{ __('erp.current_year') }}</small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 col-12">
        <div class="kpi-box">
            <div class="kpi-icon" style="background-color: #fee2e2; color: #dc2626;"><i class="fas fa-arrow-trend-down"></i></div>
            <div class="kpi-title">{{ __('erp.expense') }}</div>
            <div class="kpi-value text-danger">Rp {{ number_format($kpi['pengeluaran'] ?? 0, 0, ',', '.') }}</div>
            <small class="text-muted">{{ __('erp.current_year') }}</small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 col-12">
        <div class="kpi-box">
            <div class="kpi-icon" style="background-color: #e0f2fe; color: #2563eb;"><i class="fas fa-book-open"></i></div>
            <div class="kpi-title">{{ __('erp.journal_transaction') }}</div>
            <div class="kpi-value">{{ number_format($kpi['journal_count'] ?? 0, 0, ',', '.') }}</div>
            <small class="text-muted">{{ __('erp.total_entries_recorded') }}</small>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6 col-12">
        <div class="kpi-box">
            <div class="kpi-icon" style="background-color: #fef3c7; color: #ea580c;"><i class="fas fa-clock"></i></div>
            <div class="kpi-title">{{ __('erp.pending_pp') }}</div>
            <div class="kpi-value">{{ $kpi['pp_pending_count'] ?? 0 }} <span style="font-size: 14px; color: #64748b;">{{ __('erp.data') }}</span></div>
            <small class="text-muted">Rp {{ number_format($kpi['pp_pending_sum'] ?? 0, 0, ',', '.') }}</small>
        </div>
    </div>
</div>

{{-- ── 2. GRAFIK DINAMIS & PAYMENT PLAN ── --}}
<div class="row mb-4 g-3">
    {{-- GRAFIK ARUS KAS --}}
    <div class="col-xl-6 col-lg-12 col-12">
        <div class="dash-card">
            <div class="dash-header d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-money-bill-trend-up me-2 text-success"></i> {{ __('erp.cash_flow_chart') }}</h5>
                <div class="d-flex gap-2">
                    <select id="filterYear" class="form-select form-select-sm" onchange="loadChartData()">
                        @for($y = date('Y'); $y >= date('Y') - 4; $y--)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                    <select id="filterInterval" class="form-select form-select-sm" onchange="loadChartData()">
                        <option value="bulanan" selected>{{ __('erp.monthly_label') }}</option>
                        <option value="kuartal">{{ __('erp.quarterly_label') }}</option>
                    </select>
                </div>
            </div>
            <div class="dash-body-pad">
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="chartArusKas"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- GRAFIK LABA RUGI --}}
    <div class="col-xl-6 col-lg-12 col-12">
        <div class="dash-card">
            <div class="dash-header d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-chart-line me-2 text-primary"></i> {{ __('erp.profit_loss_chart') }}</h5>
                <a href="{{ route('laba-rugi.index') }}"><span class="badge bg-primary">{{ __('erp.bc_detail') }} <i class="fas fa-arrow-right ms-1"></i></span></a>
            </div>
            <div class="dash-body-pad">
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="chartLabaRugi"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- PAYMENT PLAN --}}
    <div class="col-12">
        <div class="dash-card">
            <div class="dash-header">
                <h5><i class="fas fa-money-check-dollar me-2 text-warning"></i> {{ __('erp.payment_plan') }}</h5>
                <a href="{{ route('payment.index') }}">{{ __('erp.view') }} <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="dash-body">
                <ul class="scroll-list">
                    @if(isset($payments))
                        @forelse($payments as $pp)
                        <li class="scroll-list-item">
                            <div style="flex: 1; min-width: 0;">
                                <h6 style="margin: 0 0 3px 0; font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $pp->keterangan }}</h6>
                                <small class="text-muted">{{ $pp->nama_divisi }} • {{ \Carbon\Carbon::parse($pp->tgl_pengajuan)->format('d M') }}</small>
                            </div>
                            <div class="text-end">
                                <div style="font-size: 0.85rem; font-weight: 700;">Rp {{ number_format($pp->nominal, 0, ',', '.') }}</div>
                                @if($pp->status_payment == 'PAID')
                                    <span class="badge bg-success text-white">{{ __('erp.status_paid') }}</span>
                                @elseif($pp->status_payment == 'APPROVED')
                                    <span class="badge bg-primary text-white">{{ __('erp.status_approved') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">{{ $pp->status_payment }}</span>
                                @endif
                            </div>
                        </li>
                        @empty
                        <li class="text-center text-muted p-4"><small>{{ __('erp.no_recent_submission') }}</small></li>
                        @endforelse
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- ── 3. TABEL JURNAL & AKSI CEPAT ── --}}
<div class="row mb-4 g-3">
    <div class="col-xl-8 col-lg-7 col-12">
        <div class="dash-card">
            <div class="dash-header">
                <h5><i class="fas fa-receipt me-2 text-info"></i> {{ __('erp.recent_journal_activity') }}</h5>
                <a href="{{ route('jurnal.index') }}">{{ __('erp.open_journal') }} <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="dash-body">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap m-0" style="font-size: 0.85rem;">
                        <thead style="background-color: #f8fafc; color: #64748b;">
                            <tr>
                                <th class="border-0 px-4 py-3">{{ __('erp.date') }}</th>
                                <th class="border-0 py-3">{{ __('erp.evidence_no') }}</th>
                                <th class="border-0 py-3">{{ __('erp.transaction_description') }}</th>
                                <th class="border-0 text-end px-4 py-3">{{ __('erp.total_value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(isset($journals))
                                @forelse($journals as $j)
                                <tr>
                                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($j->transaction_date)->format('d M Y') }}</td>
                                    <td class="py-3"><span style="font-family: monospace; font-weight: 600; color: #64748b;">{{ $j->evidence_number ?? '-' }}</span></td>
                                    <td class="py-3">{{ $j->description }}</td>
                                    <td class="text-end px-4 py-3" style="font-weight: 700; color: #0f172a;">
                                        Rp {{ number_format($j->details->where('position','DEBET')->sum('amount'), 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted p-4">{{ __('erp.no_recent_journal_activity') }}</td></tr>
                                @endforelse
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5 col-12">
        <div class="dash-card">
            <div class="dash-header">
                <h5><i class="fas fa-bolt me-2 text-warning"></i> {{ __('erp.quick_menu') }}</h5>
            </div>
            <div class="dash-body-pad">
                <div class="row g-2">
                    <div class="col-6"><a href="{{ route('jurnal.create') }}" class="btn-quick"><i class="fas fa-plus-circle text-primary"></i> {{ __('erp.general_journal') }}</a></div>
                    <div class="col-6"><a href="{{ route('payment.create') }}" class="btn-quick"><i class="fas fa-file-invoice-dollar text-warning"></i> {{ __('erp.payment_plan') }}</a></div>
                    <div class="col-6"><a href="{{ route('account.opening_balance') }}" class="btn-quick"><i class="fas fa-scale-balanced text-success"></i> {{ __('erp.opening_balance') }}</a></div>
                    <div class="col-6"><a href="{{ route('account.create') }}" class="btn-quick"><i class="fas fa-folder-plus text-info"></i> {{ __('erp.account_data') }}</a></div>
                    <div class="col-6"><a href="{{ route('laba-rugi.index') }}" class="btn-quick"><i class="fas fa-chart-line text-danger"></i> {{ __('erp.profit_loss') }}</a></div>
                    <div class="col-6"><a href="{{ route('neraca.index') }}" class="btn-quick"><i class="fas fa-balance-scale text-secondary"></i> {{ __('erp.balance_sheet') }}</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── 4. ASET TETAP & STATISTIK SISTEM ── --}}
<div class="row g-3">
    <div class="col-lg-6 col-12">
        <div class="dash-card">
            <div class="dash-header">
                <h5><i class="fas fa-boxes-stacked me-2 text-purple" style="color: #9333ea;"></i> {{ __('erp.fixed_asset') }}</h5>
                <a href="{{ route('aset.index') }}">{{ __('erp.manage') }} <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
            <div class="dash-body">
                <ul class="scroll-list" style="max-height: 250px;">
                    @if(isset($assets))
                        @forelse($assets as $ast)
                        @php $pct = $ast->depreciation_pct; @endphp
                        <li class="scroll-list-item">
                            <div style="width: 42px; height: 42px; border-radius: 8px; background: #e0f2fe; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                <i class="fas fa-cube"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h6 style="margin: 0; font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $ast->asset_name }}</h6>
                                <div class="progress mt-2" style="height: 6px; background-color: #f1f5f9;">
                                    <div class="progress-bar {{ $pct < 50 ? 'bg-primary' : ($pct < 80 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                            <div style="font-weight: 700; font-size: 0.85rem; color: {{ $pct < 50 ? '#2563eb' : ($pct < 80 ? '#d97706' : '#dc2626') }};">{{ $pct }}%</div>
                        </li>
                        @empty
                        <li class="text-center text-muted p-4"><small>{{ __('erp.no_fixed_asset_data') }}</small></li>
                        @endforelse
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6 col-12">
        <div class="dash-card">
            <div class="dash-header">
                <h5><i class="fas fa-chart-pie me-2 text-success"></i> {{ __('erp.database_summary') }}</h5>
            </div>
            <div class="dash-body-pad">
                <div class="row">
                    <div class="col-sm-6 col-12 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-list-check fa-2x me-3 text-primary" style="opacity: 0.8;"></i>
                            <div>
                                <small class="text-muted d-block fw-bold">{{ __('erp.coa_structure') }}</small>
                                <span style="font-size: 1.1rem; font-weight: 700;">{{ isset($stats['total_akun']) ? number_format($stats['total_akun']) : 0 }} {{ __('erp.account_count') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-12 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-tags fa-2x me-3 text-success" style="opacity: 0.8;"></i>
                            <div>
                                <small class="text-muted d-block fw-bold">{{ __('erp.helper_code') }}</small>
                                <span style="font-size: 1.1rem; font-weight: 700;">{{ isset($stats['total_helper']) ? number_format($stats['total_helper']) : 0 }} {{ __('erp.entities') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-12 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-sitemap fa-2x me-3 text-warning" style="opacity: 0.8;"></i>
                            <div>
                                <small class="text-muted d-block fw-bold">{{ __('erp.active_division') }}</small>
                                <span style="font-size: 1.1rem; font-weight: 700;">{{ $stats['total_divisi'] ?? 0 }} {{ __('erp.master') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-12 mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-invoice-dollar fa-2x me-3 text-secondary" style="opacity: 0.8;"></i>
                            <div>
                                <small class="text-muted d-block fw-bold">{{ __('erp.payment_plan') }}</small>
                                <span style="font-size: 1.1rem; font-weight: 700;">{{ isset($stats['total_pp']) ? number_format($stats['total_pp']) : 0 }} {{ __('erp.data') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

{{-- ── SCRIPT CHART.JS DINAMIS ── --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
let chartCashInstance = null;
let chartPLInstance = null;

function getChartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    boxWidth: 10,
                    font: { size: 11 }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: false,
                grid: { color: '#f1f5f9' },
                ticks: {
                    callback: function(v) {
                        return 'Rp ' + v + 'jt';
                    }
                }
            },
            x: {
                grid: { display: false }
            }
        }
    };
}

function initCharts() {
    const ctxCash = document.getElementById('chartArusKas');
    const ctxPL = document.getElementById('chartLabaRugi');

    // Inisialisasi Chart Kosong (Struktur)
    chartCashInstance = new Chart(ctxCash, {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: getChartOptions()
    });

    chartPLInstance = new Chart(ctxPL, {
        type: 'bar',
        data: { labels: [], datasets: [] },
        options: getChartOptions()
    });
}

function loadChartData() {
    const year = document.getElementById('filterYear').value;
    const interval = document.getElementById('filterInterval').value;

    fetch('{{ route('dashboard.chart-data') }}?year=' + year + '&interval=' + interval)
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            // Update Data Arus Kas
            chartCashInstance.data.labels = data.labels;
            chartCashInstance.data.datasets = [
                {
                    label: 'Kas Masuk (jt)',
                    data: data.arusKas.in,
                    backgroundColor: 'rgba(22, 163, 74, 0.15)',
                    borderColor: '#16a34a',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Kas Keluar (jt)',
                    data: data.arusKas.out,
                    backgroundColor: 'rgba(220, 38, 38, 0.15)',
                    borderColor: '#dc2626',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Net Cash (jt)',
                    data: data.arusKas.net,
                    borderColor: '#2563eb',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    type: 'line',
                    pointRadius: 3,
                    tension: 0.3
                }
            ];
            chartCashInstance.update();

            // Update Data Laba Rugi
            chartPLInstance.data.labels = data.labels;
            chartPLInstance.data.datasets = [
                {
                    label: 'Pendapatan (jt)',
                    data: data.labaRugi.revenue,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Beban/HPP (jt)',
                    data: data.labaRugi.expense,
                    backgroundColor: 'rgba(249, 115, 22, 0.2)',
                    borderColor: '#f97316',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Laba Bersih (jt)',
                    data: data.labaRugi.net,
                    borderColor: '#a855f7',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    type: 'line',
                    pointRadius: 3,
                    tension: 0.3
                }
            ];
            chartPLInstance.update();
        })
        .catch(function(error) {
            console.error('Gagal mengambil data chart:', error);
        });
}

// Jalankan saat halaman pertama dimuat
document.addEventListener('DOMContentLoaded', function() {
    initCharts();
    loadChartData();
});
</script>
@endsection
