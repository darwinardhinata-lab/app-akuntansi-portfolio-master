<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Support\ReportInterval;
use Illuminate\Support\Facades\DB;

class BalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        // 1. TINGKATKAN BATAS NAFAS SERVER
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');

        $tab      = $request->get('tab', 'bulanan');
        $isExport = $request->get('export') == 'excel';

        // 2. Tarik Master Akun Neraca
        $masterAccounts = Account::whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['1', '2', '3'])
            ->get()->keyBy(fn($item) => trim($item->account_code));

        if ($tab == 'bulanan') {
            $year     = (int) $request->get('year', date('Y'));
            $interval = $request->get('interval', 'bulanan');
            if (!ReportInterval::isValid($interval)) $interval = 'bulanan';
            $month = (int) $request->get('month', date('n'));

            [$rangeStart, $rangeEnd] = ReportInterval::range($interval, $year, $month);
            $periodExpr = ReportInterval::periodKeyExpr($interval, 'journal_headers.transaction_date');
            $periods    = ReportInterval::buildPeriods($interval, $year, $month);

            // ==============================================================
            // 1. SALDO AWAL ASET, KEWAJIBAN, MODAL
            // Mengambil semua nilai kumulatif sebelum tanggal $rangeStart
            // ==============================================================
            $openingRows = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(DB::raw('TRIM(account_code) as account_code'), 'position', DB::raw('SUM(amount) as total'))
                ->where('journal_headers.transaction_date', '<', $rangeStart . ' 00:00:00')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['1', '2', '3'])
                ->groupBy(DB::raw('TRIM(account_code)'), 'position')
                ->get();

            // ==============================================================
            // 2. PERGERAKAN ASET, KEWAJIBAN, MODAL (Dalam Rentang)
            // ==============================================================
            $allData = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(
                    DB::raw('TRIM(account_code) as account_code'),
                    'position',
                    DB::raw($periodExpr . ' as period_key'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('journal_headers.transaction_date', '>=', $rangeStart . ' 00:00:00')
                ->where('journal_headers.transaction_date', '<=', $rangeEnd . ' 23:59:59')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['1', '2', '3'])
                ->groupBy(DB::raw('TRIM(account_code)'), 'position', DB::raw($periodExpr))
                ->get();

            // ==============================================================
            // 3. KALKULASI LABA RUGI (DIPISAH MENJADI 3 TAHAP AGAR BALANCE)
            // ==============================================================
            $yearOfRangeStart = (int) date('Y', strtotime($rangeStart));

            // TAHAP A: Laba Ditahan (Seluruh P&L sebelum tahun $rangeStart dimulai)
            $plRetained = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select('position', DB::raw('SUM(amount) as total'))
                ->where('journal_headers.transaction_date', '<', $yearOfRangeStart . '-01-01 00:00:00')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['4', '5', '6', '7', '8', '9'])
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy('position')
                ->get();

            // TAHAP B: Laba Berjalan Awal (YTD sebelum $rangeStart, sangat krusial untuk interval harian)
            $plCurrentYearOpening = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select('position', DB::raw('SUM(amount) as total'))
                ->where('journal_headers.transaction_date', '>=', $yearOfRangeStart . '-01-01 00:00:00')
                ->where('journal_headers.transaction_date', '<', $rangeStart . ' 00:00:00')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['4', '5', '6', '7', '8', '9'])
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy('position')
                ->get();

            // TAHAP C: Pergerakan Laba Berjalan (Di dalam rentang)
            $plData = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(
                    'position',
                    DB::raw($periodExpr . ' as period_key'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('journal_headers.transaction_date', '>=', $rangeStart . ' 00:00:00')
                ->where('journal_headers.transaction_date', '<=', $rangeEnd . ' 23:59:59')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['4', '5', '6', '7', '8', '9'])
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy('position', DB::raw($periodExpr))
                ->get();

            $dataCodes = $allData->pluck('account_code')->unique();
        } else {
            $date = $request->get('date', date('Y-m-d'));
            $allData = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(DB::raw('TRIM(account_code) as account_code'), 'position', DB::raw('SUM(amount) as total'))
                ->where('journal_headers.transaction_date', '<=', $date . ' 23:59:59')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['1', '2', '3'])
                ->groupBy(DB::raw('TRIM(account_code)'), 'position')
                ->get();
            $dataCodes = $allData->pluck('account_code')->unique();
        }

        $allCodes = collect($masterAccounts->keys())->merge($dataCodes)->unique();

        $accountsToProcess = [];
        foreach ($allCodes as $code) {
            $prefix = substr($code, 0, 1);
            if (in_array($prefix, ['1', '2', '3'])) {
                $accountsToProcess[] = (object) [
                    'account_code' => $code,
                    'account_name' => $masterAccounts->has($code) ? $masterAccounts[$code]->account_name : 'Akun Tidak Dikenal',
                ];
            }
        }

        usort($accountsToProcess, fn($a, $b) => $a->account_code <=> $b->account_code);

        if ($tab == 'bulanan') {
            $report = $this->generateMatrix($accountsToProcess, $allData, $openingRows, $plData, $plRetained, $plCurrentYearOpening, $periods);
            $view = view('report.balance-sheet', compact('tab', 'report', 'year', 'interval', 'month', 'periods', 'isExport'));
        } else {
            $report = $this->generatePeriodeData($accountsToProcess, $allData, $date);
            $view = view('report.balance-sheet', compact('tab', 'report', 'date', 'isExport'));
        }

        if ($isExport) {
            $fname = $tab == 'bulanan' ? 'Neraca_Matriks_' . $interval . '_' . $year . '.xls' : 'Neraca_' . $date . '.xls';
            return response($view)->header('Content-Type', 'application/vnd.ms-excel')
                ->header('Content-Disposition', 'attachment; filename="' . $fname . '"');
        }
        return $view;
    }

    private function generateMatrix($accounts, $allData, $openingRows, $plData, $plRetained, $plCurrentYearOpening, array $periods)
    {
        $periodKeys = array_column($periods, 'key');
        $emptyTotals = array_fill_keys($periodKeys, null);

        $structure = [
            'aset'          => ['items' => [], 'totals' => $emptyTotals],
            'kewajiban'     => ['items' => [], 'totals' => $emptyTotals],
            'modal'         => ['items' => [], 'totals' => $emptyTotals],
            'laba_ditahan'  => $emptyTotals,
            'laba_berjalan' => $emptyTotals,
        ];

        $referenceDate = date('Y-m-d');

        // Fast Lookup: Aset / Kewajiban / Modal (Dalam Rentang)
        $fastData = [];
        foreach ($allData as $item) {
            $code = trim($item->account_code);
            $pk   = (string) $item->period_key;
            $pos  = strtoupper(trim($item->position)); 
            if (!isset($fastData[$code][$pk]['DEBET']))  $fastData[$code][$pk]['DEBET']  = 0;
            if (!isset($fastData[$code][$pk]['KREDIT'])) $fastData[$code][$pk]['KREDIT'] = 0;
            $fastData[$code][$pk][$pos] += (float) $item->total;
        }

        // Fast Lookup: Aset / Kewajiban / Modal (Saldo Awal)
        $openingBalance = [];
        foreach ($openingRows as $row) {
            $code = trim($row->account_code);
            $pos  = strtoupper(trim($row->position)); 
            if (!isset($openingBalance[$code]['DEBET']))  $openingBalance[$code]['DEBET']  = 0;
            if (!isset($openingBalance[$code]['KREDIT'])) $openingBalance[$code]['KREDIT'] = 0;
            $openingBalance[$code][$pos] += (float) $row->total;
        }

        foreach ($accounts as $acc) {
            $code          = trim($acc->account_code);
            $prefix        = substr($code, 0, 1);
            $isDebetNormal = ($prefix == '1');
            $group         = ($prefix == '1') ? 'aset' : (($prefix == '2') ? 'kewajiban' : 'modal');

            $periodVals = [];
            $hasData    = false;

            $cumDeb = $openingBalance[$code]['DEBET'] ?? 0;
            $cumKre = $openingBalance[$code]['KREDIT'] ?? 0;

            foreach ($periods as $p) {
                $pk = $p['key'];

                if (ReportInterval::isFuturePeriod($p, $referenceDate)) {
                    $periodVals[$pk] = null;
                    continue;
                }

                if (isset($fastData[$code][$pk]['DEBET']))  $cumDeb += (float) $fastData[$code][$pk]['DEBET'];
                if (isset($fastData[$code][$pk]['KREDIT'])) $cumKre += (float) $fastData[$code][$pk]['KREDIT'];

                $balance = $isDebetNormal ? ($cumDeb - $cumKre) : ($cumKre - $cumDeb);
                $balanceRounded = round($balance, 2);

                if ($balanceRounded != 0) $hasData = true;
                $periodVals[$pk] = $balanceRounded;

                if ($structure[$group]['totals'][$pk] === null) $structure[$group]['totals'][$pk] = 0;
                $structure[$group]['totals'][$pk] += $balanceRounded;
                $structure[$group]['totals'][$pk] = round($structure[$group]['totals'][$pk], 2);
            }

            if ($hasData) {
                $structure[$group]['items'][] = ['code' => $code, 'name' => $acc->account_name, 'periods' => $periodVals];
            }
        }

        // Fast Lookup: Laba Rugi Berjalan (Dalam Rentang)
        $fastPl = [];
        foreach ($plData as $row) {
            $pk = (string) $row->period_key;
            $pos = strtoupper(trim($row->position)); 
            if (!isset($fastPl[$pk][$pos])) $fastPl[$pk][$pos] = 0;
            $fastPl[$pk][$pos] += (float) $row->total;
        }

        // Hitung Base Laba Ditahan
        $baseLabaDitahan = 0;
        foreach ($plRetained as $row) {
            $amount = (float) $row->total;
            $pos = strtoupper(trim($row->position));
            $baseLabaDitahan += ($pos == 'KREDIT') ? $amount : -$amount;
        }

        // Hitung Base Laba Berjalan (YTD)
        $baseLabaBerjalan = 0;
        foreach ($plCurrentYearOpening as $row) {
            $amount = (float) $row->total;
            $pos = strtoupper(trim($row->position));
            $baseLabaBerjalan += ($pos == 'KREDIT') ? $amount : -$amount;
        }

        $runningLabaBerjalan = $baseLabaBerjalan;
        $runningLabaDitahan  = $baseLabaDitahan;
        $runningYear         = (int) date('Y', strtotime($periods[0]['start']));

        foreach ($periods as $p) {
            $pk = $p['key'];

            if (ReportInterval::isFuturePeriod($p, $referenceDate)) {
                $structure['laba_berjalan'][$pk] = null;
                $structure['laba_ditahan'][$pk]  = null;
                continue;
            }

            $periodYear = (int) date('Y', strtotime($p['end']));

            // Jika periode yang diproses memasuki tahun baru, pindahkan Laba Berjalan jadi Laba Ditahan
            if ($runningYear !== $periodYear) {
                $runningLabaDitahan += $runningLabaBerjalan;
                $runningLabaBerjalan = 0; 
                $runningYear = $periodYear;
            }

            $kre = $fastPl[$pk]['KREDIT'] ?? 0;
            $deb = $fastPl[$pk]['DEBET'] ?? 0;
            $runningLabaBerjalan += ($kre - $deb);

            $laba        = round($runningLabaBerjalan, 2);
            $labaDitahan = round($runningLabaDitahan, 2);

            $structure['laba_berjalan'][$pk] = $laba;
            $structure['laba_ditahan'][$pk]  = $labaDitahan;

            if ($structure['modal']['totals'][$pk] === null) $structure['modal']['totals'][$pk] = 0;
            $structure['modal']['totals'][$pk] += $laba + $labaDitahan;
            $structure['modal']['totals'][$pk] = round($structure['modal']['totals'][$pk], 2);
        }

        return $structure;
    }

    private function generatePeriodeData($accounts, $allData, $date)
    {
        $structure = [
            'aset' => ['items' => [], 'total' => 0],
            'kewajiban' => ['items' => [], 'total' => 0],
            'modal' => ['items' => [], 'total' => 0],
            'laba_ditahan' => 0,
            'laba_berjalan' => 0
        ];

        foreach ($accounts as $acc) {
            $prefix = substr(trim($acc->account_code), 0, 1);
            $isDebetNormal = ($prefix == '1');

            $filtered = $allData->filter(function ($item) use ($acc) {
                return trim($item->account_code) === trim($acc->account_code);
            });

            $deb = $filtered->filter(fn($i) => strtoupper(trim($i->position)) == 'DEBET')->sum('total');
            $kre = $filtered->filter(fn($i) => strtoupper(trim($i->position)) == 'KREDIT')->sum('total');

            $balance = $isDebetNormal ? ($deb - $kre) : ($kre - $deb);
            $balanceRounded = round($balance, 2);

            if ($balanceRounded != 0) {
                $group = ($prefix == '1') ? 'aset' : (($prefix == '2') ? 'kewajiban' : 'modal');
                $structure[$group]['items'][] = ['code' => $acc->account_code, 'name' => $acc->account_name, 'balance' => $balanceRounded];
                $structure[$group]['total'] += $balanceRounded;
                $structure[$group]['total'] = round($structure[$group]['total'], 2);
            }
        }

        $year = (int) date('Y', strtotime($date));
        $profits = $this->calculateSplitProfits($date, $year);
        $structure['laba_ditahan'] = $profits['laba_ditahan'];
        $structure['laba_berjalan'] = $profits['laba_berjalan'];
        $structure['modal']['total'] += $structure['laba_ditahan'] + $structure['laba_berjalan'];
        $structure['modal']['total'] = round($structure['modal']['total'], 2);

        return $structure;
    }

    private function calculateSplitProfits($date, $year)
    {
        $data = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select('position', DB::raw("YEAR(journal_headers.transaction_date) as year"), DB::raw('SUM(amount) as total'))
                ->where('journal_headers.transaction_date', '<=', $date . ' 23:59:59')
                ->whereIn(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), ['4', '5', '6', '7', '8', '9'])
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy('position', DB::raw("YEAR(journal_headers.transaction_date)"))
                ->get();

        $totalLabaDitahan = 0;
        $totalLabaBerjalan = 0;

        foreach ($data as $row) {
            $amount = round((float) $row->total, 2);
            $pos = strtoupper(trim($row->position));
            if ($row->year < $year) {
                if ($pos == 'KREDIT') $totalLabaDitahan += $amount;
                else $totalLabaDitahan -= $amount;
            } else {
                if ($pos == 'KREDIT') $totalLabaBerjalan += $amount;
                else $totalLabaBerjalan -= $amount;
            }
        }

        return [
            'laba_ditahan' => round($totalLabaDitahan, 2),
            'laba_berjalan' => round($totalLabaBerjalan, 2)
        ];
    }
}