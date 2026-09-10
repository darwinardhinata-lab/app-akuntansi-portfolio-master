@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.budgeting') => null]" />
@endsection

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-0 mb-5">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">{{ __('erp.executive_dashboard_budgeting') }}</h3>
            <p class="text-muted small mb-0">
                <i class="fa-solid fa-calendar-days me-1 text-primary"></i> 
                Berdasarkan Data Historis: <span class="fw-bold text-dark">{{ date('d M Y', strtotime($startDate)) }}</span> {{ __('erp.to_abbr') }} <span class="fw-bold text-dark">{{ date('d M Y', strtotime($endDate)) }}</span> ({{ $monthCount }} Bulan)
            </p>
        </div>
    </div>

    <form action="{{ route('budgeting.index') }}" method="GET" class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.analysis_since') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm fw-bold" value="{{ $startDate }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.until_label') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm fw-bold" value="{{ $endDate }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.forecasting_method') }}</label>
                <select name="method" class="form-select form-select-sm fw-bold" onchange="this.form.submit()">
                    <option value="simple_avg" {{ request('method', 'simple_avg') == 'simple_avg' ? 'selected' : '' }}>
                        Simple Average (Default)
                    </option>
                    <option value="weighted_avg" {{ request('method') == 'weighted_avg' ? 'selected' : '' }}>
                        Weighted Moving Average (Rekomendasi)
                    </option>
                    <option value="exponential_smoothing" {{ request('method') == 'exponential_smoothing' ? 'selected' : '' }}>
                        Exponential Smoothing
                    </option>
                    <option value="linear_regression" {{ request('method') == 'linear_regression' ? 'selected' : '' }}>
                        Linear Regression
                    </option>
                    <option value="median" {{ request('method') == 'median' ? 'selected' : '' }}>
                        Median Forecast
                    </option>
                </select>
                <small class="text-muted">{{ __('erp.auto_submit_on_select') }}</small>
            </div>
            <div class="col-md-2 d-flex align-items-end mt-4 pt-1">
                <button type="submit" class="btn btn-sm btn-primary fw-bold w-100 shadow-sm"><i class="fa-solid fa-calculator me-2"></i> {{ __('erp.calculate_btn') }}</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 5px solid #0ea5e9 !important;">
                <div class="card-body p-3">
                    <p class="text-muted small fw-bold text-uppercase mb-1">{{ __('erp.revenue_target') }}</p>
                    <h5 class="fw-bold text-dark mb-0">Rp {{ number_format($summary['pendapatan'], 0, ',', '.') }}</h5>
                    <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $methodUsed ?? 'simple_avg')) }}</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 5px solid #f59e0b !important;">
                <div class="card-body p-3">
                    <p class="text-muted small fw-bold text-uppercase mb-1">{{ __('erp.cogs_budget_max') }}</p>
                    <h5 class="fw-bold text-dark mb-0">Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</h5>
                    @if(isset($trendInfo) && $trendInfo)
                        <small class="text-{{ $trendInfo['trend_direction'] == 'increasing' ? 'success' : ($trendInfo['trend_direction'] == 'decreasing' ? 'danger' : 'muted') }}">
                            <i class="fa-solid fa-{{ $trendInfo['trend_direction'] == 'increasing' ? 'arrow-trend-up' : ($trendInfo['trend_direction'] == 'decreasing' ? 'arrow-trend-down' : 'equals') }}"></i>
                            {{ $trendInfo['trend_direction'] ?? 'stable' }}
                        </small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-left: 5px solid #ef4444 !important;">
                <div class="card-body p-3">
                    <p class="text-muted small fw-bold text-uppercase mb-1">{{ __('erp.operational_budget_max') }}</p>
                    <h5 class="fw-bold text-dark mb-0">Rp {{ number_format($summary['opex'], 0, ',', '.') }}</h5>
                    @if(isset($trendInfo) && isset($trendInfo['confidence_r2']))
                        <small class="text-muted">R²: {{ number_format($trendInfo['confidence_r2'] ?? 0, 2) }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 {{ $summary['laba_bersih'] < 0 ? 'bg-danger text-white' : 'bg-success text-white' }}" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <p class="small fw-bold text-uppercase mb-1" style="opacity: 0.8;">{{ __('erp.net_profit_projection') }}</p>
                    <h5 class="fw-bold mb-0">Rp {{ number_format($summary['laba_bersih'], 0, ',', '.') }}</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-muted mb-4"><i class="fa-solid fa-scale-balanced me-2"></i> {{ __('erp.projection_map_revenue_vs_expense') }}</h6>
                    <canvas id="barChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-muted mb-4"><i class="fa-solid fa-fire me-2 text-danger"></i> {{ __('erp.top6_operational_cost_drains') }}</h6>
                    <canvas id="pieChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3" style="color: #0f172a;"><i class="fa-solid fa-table-list me-2"></i> {{ __('erp.projection_budget_detail_per_account') }}</h5>
    <div class="row g-4">
        
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-primary"><i class="fa-solid fa-arrow-trend-up me-1"></i> {{ __('erp.sales_target') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>{{ __('erp.account') }}</th>
                                <th class="text-end">{{ __('erp.total_historical') }}</th>
                                <th class="text-end pe-3">{{ __('erp.average_per_month') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalHistorisPenjualan = 0; @endphp
                            @foreach($report['penjualan']['items'] as $item)
                            @php $totalHistorisPenjualan += $item['total']; @endphp
                            <tr>
                                <td>{{ $item['name'] }} <span class="text-muted d-block" style="font-size: 0.75rem;">[{{ $item['code'] }}]</span></td>
                                <td class="text-end">{{ number_format($item['total'], 0, ',', '.') }}</td>
                                <td class="text-end pe-3 fw-bold text-primary">{{ number_format($item['average'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold border-top" style="font-size: 0.8rem;">
                            <tr class="text-dark">
                                <td>{{ __('erp.total_sales_caps') }}</td>
                                <td class="text-end">{{ number_format($totalHistorisPenjualan, 0, ',', '.') }}</td>
                                <td class="text-end pe-3 text-primary" style="font-size: 0.85rem;">{{ number_format($report['penjualan']['total_avg'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-warning"><i class="fa-solid fa-basket-shopping me-1"></i> {{ __('erp.cogs_budget_goods') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>{{ __('erp.account') }}</th>
                                <th class="text-end">{{ __('erp.total_historical') }}</th>
                                <th class="text-end pe-3">{{ __('erp.limit_per_month') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalHistorisPembelian = 0; @endphp
                            @foreach($report['pembelian']['items'] as $item)
                            @php $totalHistorisPembelian += $item['total']; @endphp
                            <tr>
                                <td>{{ $item['name'] }} <span class="text-muted d-block" style="font-size: 0.75rem;">[{{ $item['code'] }}]</span></td>
                                <td class="text-end">{{ number_format($item['total'], 0, ',', '.') }}</td>
                                <td class="text-end pe-3 fw-bold text-warning">{{ number_format($item['average'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold border-top" style="font-size: 0.8rem;">
                            <tr class="text-dark">
                                <td>{{ __('erp.total_cogs_budget_caps') }}</td>
                                <td class="text-end">{{ number_format($totalHistorisPembelian, 0, ',', '.') }}</td>
                                <td class="text-end pe-3 text-warning" style="font-size: 0.85rem;">{{ number_format($report['pembelian']['total_avg'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-hand-holding-dollar me-1"></i> {{ __('erp.operational_budget') }}</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light text-muted">
                            <tr>
                                <th>{{ __('erp.account') }}</th>
                                <th class="text-end">{{ __('erp.total_historical') }}</th>
                                <th class="text-end pe-3">{{ __('erp.limit_per_month') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalHistorisOperasional = 0; @endphp
                            @foreach($report['operasional']['items'] as $item)
                            @php $totalHistorisOperasional += $item['total']; @endphp
                            <tr>
                                <td>{{ $item['name'] }} <span class="text-muted d-block" style="font-size: 0.75rem;">[{{ $item['code'] }}]</span></td>
                                <td class="text-end">{{ number_format($item['total'], 0, ',', '.') }}</td>
                                <td class="text-end pe-3 fw-bold text-danger">{{ number_format($item['average'], 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold border-top" style="font-size: 0.8rem;">
                            <tr class="text-dark">
                                <td>{{ __('erp.total_operational_caps') }}</td>
                                <td class="text-end">{{ number_format($totalHistorisOperasional, 0, ',', '.') }}</td>
                                <td class="text-end pe-3 text-danger" style="font-size: 0.85rem;">{{ number_format($report['operasional']['total_avg'], 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- 1. Bar Chart (Peta Proyeksi) ---
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Pendapatan', 'HPP / Persediaan', 'Biaya Operasional'],
                datasets: [{
                    label: 'Proyeksi Rupiah per Bulan',
                    data: [
                        {{ $summary['pendapatan'] }}, 
                        {{ $summary['hpp'] }}, 
                        {{ $summary['opex'] }}
                    ],
                    backgroundColor: [
                        'rgba(14, 165, 233, 0.8)', 
                        'rgba(245, 158, 11, 0.8)', 
                        'rgba(239, 68, 68, 0.8)'   
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { callback: function(value) { return 'Rp ' + (value/1000000).toFixed(0) + ' Juta'; } }
                    }
                }
            }
        });

        // --- 2. Doughnut Chart (Porsi Top Opex) ---
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($chartOpexLabels) !!},
                datasets: [{
                    data: {!! json_encode($chartOpexData) !!},
                    backgroundColor: [
                        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#06b6d4', '#6366f1'
                    ],
                    borderWidth: 2,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } }
                }
            }
        });
    });
</script>
@endsection
