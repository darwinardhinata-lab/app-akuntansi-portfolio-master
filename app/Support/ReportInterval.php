<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ReportInterval
{
    public const OPTIONS = [
        'harian'   => 'Harian',
        'mingguan' => 'Mingguan',
        'bulanan'  => 'Bulanan',
        'quarter'  => 'Quarter',
        'semester' => 'Semester',
        'tahunan'  => 'Tahunan',
    ];

    public static function isValid(string $interval): bool
    {
        return array_key_exists($interval, self::OPTIONS);
    }

    public static function buildPeriods(string $interval, int $year, ?int $month = null): array
    {
        $periods = [];
        switch ($interval) {
            case 'harian':
                $month = $month ?: (int) date('n');
                $month = max(1, min(12, $month));
                $daysInMonth = (int) date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $periods[] = ['key' => $date, 'label' => str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'start' => $date, 'end' => $date];
                }
                break;
            case 'mingguan':
                $cursor = new \DateTime("{$year}-01-01");
                $end    = new \DateTime("{$year}-12-31");
                $seen   = [];
                while ($cursor <= $end) {
                    $isoYear = (int) $cursor->format('o');
                    $isoWeek = (int) $cursor->format('W');
                    if ($isoYear == $year) {
                        $key = $isoYear . str_pad((string) $isoWeek, 2, '0', STR_PAD_LEFT);
                        if (!isset($seen[$key])) {
                            $seen[$key]  = true;
                            $weekStart   = clone $cursor;
                            $weekStart->modify('monday this week');
                            $weekEnd     = (clone $weekStart)->modify('+6 days');
                            $clippedStart = max($weekStart->format('Y-m-d'), "{$year}-01-01");
                            $clippedEnd   = min($weekEnd->format('Y-m-d'), "{$year}-12-31");
                            $periods[] = ['key' => $key, 'label' => 'M' . $isoWeek, 'start' => $clippedStart, 'end' => $clippedEnd];
                        }
                    }
                    $cursor->modify('+1 day');
                }
                break;
            case 'bulanan':
                $names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
                for ($m = 1; $m <= 12; $m++) {
                    $start = sprintf('%04d-%02d-01', $year, $m);
                    $endD  = date('Y-m-t', strtotime($start));
                    $periods[] = ['key' => sprintf('%04d-%02d', $year, $m), 'label' => $names[$m - 1], 'start' => $start, 'end' => $endD];
                }
                break;
            case 'quarter':
                for ($q = 1; $q <= 4; $q++) {
                    $startMonth = ($q - 1) * 3 + 1;
                    $start = sprintf('%04d-%02d-01', $year, $startMonth);
                    $endD  = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $startMonth + 2)));
                    $periods[] = ['key' => "{$year}-Q{$q}", 'label' => "Q{$q}", 'start' => $start, 'end' => $endD];
                }
                break;
            case 'semester':
                for ($s = 1; $s <= 2; $s++) {
                    $startMonth = $s == 1 ? 1 : 7;
                    $start = sprintf('%04d-%02d-01', $year, $startMonth);
                    $endD  = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $s == 1 ? 6 : 12)));
                    $periods[] = ['key' => "{$year}-S{$s}", 'label' => "Semester {$s}", 'start' => $start, 'end' => $endD];
                }
                break;
            case 'tahunan':
                for ($y = $year - 4; $y <= $year; $y++) {
                    $periods[] = ['key' => (string) $y, 'label' => (string) $y, 'start' => "{$y}-01-01", 'end' => "{$y}-12-31"];
                }
                break;
        }
        return $periods;
    }

    public static function periodKeyExpr(string $interval, string $dateColumn): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            switch ($interval) {
                case 'harian':   return "date({$dateColumn})";
                case 'mingguan': return "strftime('%Y%W', {$dateColumn})";
                case 'bulanan':  return "strftime('%Y-%m', {$dateColumn})";
                case 'quarter':  return "strftime('%Y', {$dateColumn}) || '-Q' || ((cast(strftime('%m', {$dateColumn}) as integer) + 2) / 3)";
                case 'semester': return "strftime('%Y', {$dateColumn}) || '-S' || (case when cast(strftime('%m', {$dateColumn}) as integer) <= 6 then 1 else 2 end)";
                case 'tahunan':  return "strftime('%Y', {$dateColumn})";
                default:         return "strftime('%Y-%m', {$dateColumn})";
            }
        }

        switch ($interval) {
            case 'harian':   return "DATE({$dateColumn})";
            case 'mingguan': return "YEARWEEK({$dateColumn}, 3)";
            case 'bulanan':  return "DATE_FORMAT({$dateColumn}, '%Y-%m')";
            case 'quarter':  return "CONCAT(YEAR({$dateColumn}), '-Q', QUARTER({$dateColumn}))";
            case 'semester': return "CONCAT(YEAR({$dateColumn}), '-S', IF(MONTH({$dateColumn}) <= 6, 1, 2))";
            case 'tahunan':  return "YEAR({$dateColumn})";
            default:         return "DATE_FORMAT({$dateColumn}, '%Y-%m')";
        }
    }

    public static function range(string $interval, int $year, ?int $month = null): array
    {
        if ($interval == 'harian') {
            $month = max(1, min(12, $month ?: (int) date('n')));
            $start = sprintf('%04d-%02d-01', $year, $month);
            return [$start, date('Y-m-t', strtotime($start))];
        }
        if ($interval == 'tahunan') return [($year - 4) . '-01-01', $year . '-12-31'];
        return [$year . '-01-01', $year . '-12-31'];
    }

    public static function openingCutoff(string $interval, int $year, ?int $month = null): string { return self::range($interval, $year, $month)[0]; }
    public static function isFuturePeriod(array $period, ?string $referenceDate = null): bool { return $period['start'] > ($referenceDate ?: date('Y-m-d')); }
    public static function rangeLabel(string $interval, int $year, ?int $month = null): string
    {
        if ($interval == 'harian') {
            $namesFull = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            return $namesFull[max(1, min(12, $month ?: (int) date('n'))) - 1] . ' ' . $year;
        }
        if ($interval == 'tahunan') return ($year - 4) . ' s/d ' . $year;
        return 'Tahun ' . $year;
    }
}