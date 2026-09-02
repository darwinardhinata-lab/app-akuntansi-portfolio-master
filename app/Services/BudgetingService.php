<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\AccountClassifier;

/**
 * Service class for Budgeting and Projection calculations.
 * Provides modern and precise methods for financial forecasting.
 */
class BudgetingService
{
    /**
     * Get the category configuration for a given account code prefix.
     */
    private function getCategoryByPrefix(string $prefix, ?string $normalBalance = null): ?array
    {
        $groupInfo = AccountClassifier::determineGroup($prefix, $normalBalance);
        $group = $groupInfo['group'];
        
        return match($group) {
            'pendapatan' => ['key' => 'penjualan', 'label' => 'Pendapatan'],
            'hpp' => ['key' => 'pembelian', 'label' => 'HPP/Persediaan'],
            'biaya', 'pendapatan_lain', 'beban_lain' => ['key' => 'operasional', 'label' => 'Beban Operasional'],
            default => null,
        };
    }

    /**
     * Calculate the number of months between two dates (inclusive).
     */
    public function calculateMonthCount(Carbon $start, Carbon $end): int
    {
        return (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1;
    }

    /**
     * Run a single optimized query and return both the flat transaction collection
     * and the monthly breakdown array needed for forecasting.
     *
     * Replaces the two separate queries (getTransactionsByDateRange + getMonthlyData)
     * with one DB round-trip, halving the load on the server.
     *
     * @return array{transactions: \Illuminate\Support\Collection, monthly: array}
     */
    private function fetchAllData(Carbon $start, Carbon $end): array
    {
        // Single query: include transaction_date so we can slice by month in PHP.
        // GROUP BY date+account+position (daily granularity) avoids the DATE_FORMAT
        // MariaDB incompatibility and keeps the result set small.
        $rawData = DB::table('journal_details as jd')
            ->join('journal_headers as jh', 'jd.journal_id', '=', 'jh.journal_id')
            ->join('accounts as a', 'jd.account_code', '=', 'a.account_code')
            ->select(
                'jh.transaction_date',
                'a.account_code',
                'a.account_name',
                'a.normal_balance',
                'jd.position',
                DB::raw('SUM(jd.amount) as total_amount')
            )
            ->whereBetween('jh.transaction_date', [$start->startOfDay(), $end->endOfDay()])
            ->whereIn('jd.position', ['DEBET', 'KREDIT'])
            ->where(function ($query) {
                $query->where('a.account_code', 'like', '4%')
                    ->orWhere('a.account_code', 'like', '5%')
                    ->orWhere('a.account_code', 'like', '6%')
                    ->orWhere('a.account_code', 'like', '7%')
                    ->orWhere('a.account_code', 'like', '8%')
                    ->orWhere('a.account_code', 'like', '9%');
            })
            ->where('jh.evidence_number', 'NOT LIKE', 'SA-%')
            ->where(function ($q) {
                $q->whereNull('jh.is_opening_balance')
                  ->orWhere('jh.is_opening_balance', 0);
            })
            ->groupBy('jh.transaction_date', 'a.account_code', 'a.account_name', 'a.normal_balance', 'jd.position')
            ->get();

        // --- Build monthly breakdown (used by forecasting) ---
        $monthly = [];
        foreach ($rawData as $row) {
            $yearMonth = Carbon::parse($row->transaction_date)->format('Y-m');
            $prefix    = substr(ltrim($row->account_code, '0'), 0, 1);
            $category  = $this->getCategoryByPrefix($prefix, $row->normal_balance);
            if ($category === null) continue;

            if (!isset($monthly[$yearMonth])) {
                $monthly[$yearMonth] = [
                    'penjualan'   => [],
                    'pembelian'   => [],
                    'operasional' => [],
                ];
            }
            $monthly[$yearMonth][$category['key']][] = [
                'code'     => $row->account_code,
                'position' => $row->position,
                'amount'   => $row->total_amount,
            ];
        }

        // --- Build flat transaction collection (used by categorizeTransactions) ---
        // Re-aggregate from the daily rows so the caller sees one row per
        // account+position (same shape as the old getTransactionsByDateRange result).
        $aggregated = [];
        foreach ($rawData as $row) {
            $key = $row->account_code . '|' . $row->position;
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = (object) [
                    'account_code'   => $row->account_code,
                    'account_name'   => $row->account_name,
                    'normal_balance' => $row->normal_balance,
                    'position'       => $row->position,
                    'total_amount'   => 0,
                ];
            }
            $aggregated[$key]->total_amount += $row->total_amount;
        }

        return [
            'transactions' => collect(array_values($aggregated)),
            'monthly'      => $monthly,
        ];
    }

    /**
     * Categorize transactions into main categories.
     */
    private function categorizeTransactions($transactions, float $divisor): array
    {
        $report = [
            'penjualan' => ['items' => [], 'total_avg' => 0],
            'pembelian' => ['items' => [], 'total_avg' => 0],
            'operasional' => ['items' => [], 'total_avg' => 0]
        ];

        $grouped = $transactions->groupBy('account_code');

        foreach ($grouped as $code => $items) {
            $name = $items->first()->account_name;
            $prefix = substr(ltrim($code, '0'), 0, 1);

            $deb = $items->where('position', 'DEBET')->sum('total_amount');
            $kre = $items->where('position', 'KREDIT')->sum('total_amount');

            $normalBalance = $items->first()->normal_balance ?? null;
            $category = $this->getCategoryByPrefix($prefix, $normalBalance);

            if ($category === null) {
                continue;
            }

            $net = match($category['key']) {
                'penjualan' => $kre - $deb,
                default => $deb - $kre,
            };

            if (abs(round($net, 2)) > 0) {
                $average = round($net / $divisor, 2);
                $report[$category['key']]['items'][] = [
                    'code' => $code,
                    'name' => $name,
                    'total' => $net,
                    'average' => $average,
                ];
                $report[$category['key']]['total_avg'] += $average;
            }
        }

        foreach (['penjualan', 'pembelian', 'operasional'] as $cat) {
            usort($report[$cat]['items'], function ($a, $b) {
                return strcmp($a['code'], $b['code']);
            });
        }

        return $report;
    }

    /**
     * Calculate KPI summary for dashboard.
     */
    private function calculateSummary(array $report): array
    {
        return [
            'pendapatan' => $report['penjualan']['total_avg'],
            'hpp' => $report['pembelian']['total_avg'],
            'opex' => $report['operasional']['total_avg'],
            'laba_kotor' => $report['penjualan']['total_avg'] - $report['pembelian']['total_avg'],
            'laba_bersih' => $report['penjualan']['total_avg'] - $report['pembelian']['total_avg'] - $report['operasional']['total_avg'],
        ];
    }

    /**
     * Prepare chart data for operational expenses pie chart.
     */
    private function prepareOpexChartData(array $report): array
    {
        $chartOpex = collect($report['operasional']['items'])
            ->sortByDesc('average')
            ->take(6);

        return [
            'chartOpexLabels' => $chartOpex->pluck('name')->toArray(),
            'chartOpexData' => $chartOpex->pluck('average')->toArray(),
        ];
    }

    /**
     * Apply forecasting method to adjust averages.
     * Uses monthly data for proper forecasting.
     */
    private function applyForecastingMethod(array $report, array $monthlyData, string $method, int $monthCount): array
    {
        // Calculate monthly totals
        $monthlyTotals = [];
        foreach ($monthlyData as $month => $data) {
            $total = 0;
            foreach (['penjualan', 'pembelian', 'operasional'] as $cat) {
                foreach ($data[$cat] as $item) {
                    $amount = $item['amount'] ?? 0;
                    $adjustment = ($cat === 'penjualan') ? -1 : 1;
                    $total += $adjustment * $amount;
                }
            }
            $monthlyTotals[] = max($total, $report['penjualan']['total_avg'] / $monthCount);
        }

        if (empty($monthlyTotals)) {
            return $report;
        }

        // Calculate projection based on method
        $projection = match($method) {
            'weighted_avg' => $this->calculateWeightedMovingAverage($monthlyTotals),
            'exponential_smoothing' => $this->calculateExponentialSmoothing($monthlyTotals),
            'linear_regression' => $this->calculateLinearRegression($monthlyTotals)['projected_value'],
            'median' => $this->calculateMedianForecast($monthlyTotals),
            'holt_winters' => $this->calculateHoltWinters($monthlyTotals)['projected_value'],
            default => array_sum($monthlyTotals) / count($monthlyTotals),
        };

        // Calculate adjustment factor
        $currentAverage = $report['penjualan']['total_avg'];
        $adjustmentFactor = $projection > 0 && $currentAverage > 0 
            ? $projection / ($currentAverage / $monthCount) 
            : 1.0;

        // Apply adjustment to all categories
        foreach (['penjualan', 'pembelian', 'operasional'] as $cat) {
            $report[$cat]['total_avg'] = round($report[$cat]['total_avg'] * $adjustmentFactor, 2);
            foreach ($report[$cat]['items'] as &$item) {
                $item['average'] = round($item['average'] * $adjustmentFactor, 2);
            }
        }

        return $report;
    }

    /**
     * Main method to generate budgeting report.
     * NOW integrates forecasting methods with monthly data.
     */
    public function generateBudgetingReport(string $startDate, string $endDate, string $method = 'simple_avg'): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $monthCount = $this->calculateMonthCount($start, $end);
        $divisor = max($monthCount, 1);

        // Ensure the connection is alive before running the heavy query.
        // This prevents "MySQL server has gone away" (error 2006) caused by
        // a stale/idle connection being reused after the server dropped it.
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            DB::reconnect();
        }

        // Single query replaces the two previous separate DB calls.
        $fetched = $this->fetchAllData($start, $end);

        $report = $this->categorizeTransactions($fetched['transactions'], $divisor);
        $report = $this->applyForecastingMethod($report, $fetched['monthly'], $method, $monthCount);

        $summary   = $this->calculateSummary($report);
        $chartData = $this->prepareOpexChartData($report);

        return [
            'report'          => $report,
            'startDate'       => $startDate,
            'endDate'         => $endDate,
            'monthCount'      => $monthCount,
            'summary'         => $summary,
            'chartOpexLabels' => $chartData['chartOpexLabels'],
            'chartOpexData'   => $chartData['chartOpexData'],
            'methodUsed'      => $method,
        ];
    }

    // ==========================================
    // METODE FORECASTING
    // ==========================================

    /**
     * 1. SIMPLE MOVING AVERAGE (SMA)
     */
    public function calculateSimpleMovingAverage(array $historicalData): ?float
    {
        $values = array_values($historicalData);
        if (empty($values)) return null;
        return round(array_sum($values) / count($values), 2);
    }

    /**
     * 2. WEIGHTED MOVING AVERAGE (WMA) - REKOMENDASI
     */
    public function calculateWeightedMovingAverage(array $historicalData, int $periods = 3): ?float
    {
        $values = array_values($historicalData);
        if (count($values) < $periods) return null;

        $weights = range(1, $periods);
        $weightedSum = 0;
        $weightSum = array_sum($weights);

        for ($i = 1; $i <= $periods; $i++) {
            $weightedSum += $values[count($values) - $i] * $weights[$i - 1];
        }

        return round($weightedSum / $weightSum, 2);
    }

    /**
     * 3. EXPONENTIAL SMOOTHING - REKOMENDASI
     */
    public function calculateExponentialSmoothing(array $historicalData, float $alpha = 0.3): ?float
    {
        $values = array_values($historicalData);
        if (empty($values)) return null;

        $forecast = $values[0];
        foreach ($values as $value) {
            $forecast = $alpha * $value + (1 - $alpha) * $forecast;
        }

        return round($forecast, 2);
    }

    /**
     * 4. DOUBLE EXPONENTIAL SMOOTHING (Holt's Linear Trend)
     */
    public function calculateDoubleExponentialSmoothing(array $historicalData, float $alpha = 0.3, float $beta = 0.4): ?array
    {
        $values = array_values($historicalData);
        if (count($values) < 2) return null;

        $level = $values[0];
        $trend = $values[1] - $values[0];

        foreach ($values as $i => $value) {
            if ($i >= count($values) - 1) break;
            
            $lastLevel = $level;
            $level = $alpha * $value + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $lastLevel) + (1 - $beta) * $trend;
        }

        $nextForecast = $level + $trend;
        $trendIndicator = $trend > 0 ? 'upward' : ($trend < 0 ? 'downward' : 'stable');

        return [
            'projected_value' => round($nextForecast, 2),
            'trend' => $trendIndicator,
            'trend_magnitude' => round($trend, 2),
        ];
    }

    /**
     * 5. TRIPLE EXPONENTIAL SMOOTHING (Holt-Winters)
     */
    public function calculateHoltWinters(array $historicalData, float $alpha = 0.3, float $beta = 0.4, float $gamma = 0.5, int $seasonLength = 12): ?array
    {
        $values = array_values($historicalData);
        if (count($values) < $seasonLength + 2) {
            return [
                'projected_value' => $this->calculateWeightedMovingAverage($historicalData),
                'method_used' => 'weighted_avg',
                'reason' => 'insufficient_data_for_holt_winters',
            ];
        }

        $level = end($values);
        $trend = 0;
        $seasonals = [];

        for ($i = 0; $i < $seasonLength; $i++) {
            $seasonals[$i] = ($i < count($values)) ? ($values[$i] ?? 0) / $level : 0;
        }

        $lastSeasonal = $seasonals[(count($values) - 1) % $seasonLength];

        return [
            'projected_value' => round(($level + $trend) * $lastSeasonal, 2),
            'trend' => 'mixed',
            'seasonal_factor' => round($lastSeasonal, 4),
        ];
    }

    /**
     * 6. LINEAR REGRESSION - DENGAN CONFIDENCE LEVEL (R²)
     */
    public function calculateLinearRegression(array $historicalData): ?array
    {
        $values = array_values($historicalData);
        $n = count($values);
        
        if ($n < 2) return null;

        $x = range(1, $n);
        $y = $values;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        foreach ($x as $i => $xi) {
            $sumXY += $xi * $y[$i];
            $sumXX += $xi * $xi;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $nextX = $n + 1;
        $forecast = $slope * $nextX + $intercept;

        $yMean = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;

        foreach ($y as $i => $yi) {
            $predicted = $slope * $x[$i] + $intercept;
            $ssTotal += ($yi - $yMean) ** 2;
            $ssResidual += ($yi - $predicted) ** 2;
        }

        $rSquared = $ssTotal > 0 ? 1 - ($ssResidual / $ssTotal) : 0;

        return [
            'projected_value' => round($forecast, 2),
            'slope' => round($slope, 4),
            'trend_direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable'),
            'confidence_r2' => round($rSquared, 4),
        ];
    }

    /**
     * 7. MEDIAN FORECAST - TAHAN OUTLIER
     */
    public function calculateMedianForecast(array $historicalData): ?float
    {
        $values = array_values($historicalData);
        if (empty($values)) return null;

        sort($values);
        $count = count($values);

        if ($count % 2 === 0) {
            $median = ($values[$count/2 - 1] + $values[$count/2]) / 2;
        } else {
            $median = $values[floor($count/2)];
        }

        return round($median, 2);
    }
}