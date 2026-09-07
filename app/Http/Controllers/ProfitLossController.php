<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Illuminate\Support\Facades\DB;
use App\Support\ReportInterval;
use App\Support\AccountClassifier;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        // 1. TINGKATKAN BATAS NAFAS SERVER UNTUK EXPORT MATRIK
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $tab      = $request->get('tab', 'bulanan');
        $isExport = $request->get('export') == 'excel';

        if ($tab == 'bulanan') {
            $year     = (int) $request->get('year', date('Y'));
            $interval = $request->get('interval', 'bulanan');
            if (!ReportInterval::isValid($interval)) $interval = 'bulanan';
            $month = (int) $request->get('month', date('n'));

            [$rangeStart, $rangeEnd] = ReportInterval::range($interval, $year, $month);
            $periodExpr = ReportInterval::periodKeyExpr($interval, 'transaction_date');

            $data = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(
                    DB::raw('TRIM(account_code) as account_code'),
                    'position',
                    DB::raw($periodExpr . ' as period_key'),
                    DB::raw('SUM(amount) as total')
                )
                ->where('transaction_date', '>=', $rangeStart . ' 00:00:00')
                ->where('transaction_date', '<=', $rangeEnd . ' 23:59:59')
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy(DB::raw('TRIM(account_code)'), 'position', DB::raw($periodExpr))
                ->get();

            $periods = ReportInterval::buildPeriods($interval, $year, $month);
        } else {
            $rawStart  = $request->get('start_date', date('Y-m-01'));
            $rawEnd    = $request->get('end_date', date('Y-m-t'));
            
            $startDate = str_contains($rawStart, '/') ? \Carbon\Carbon::createFromFormat('d/m/Y', $rawStart)->format('Y-m-d') : $rawStart;
            $endDate   = str_contains($rawEnd, '/') ? \Carbon\Carbon::createFromFormat('d/m/Y', $rawEnd)->format('Y-m-d') : $rawEnd;
            
            $data = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(DB::raw('TRIM(account_code) as account_code'), 'position', DB::raw('SUM(amount) as total'))
                ->where('transaction_date', '>=', $startDate . ' 00:00:00')
                ->where('transaction_date', '<=', $endDate . ' 23:59:59')
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy(DB::raw('TRIM(account_code)'), 'position')
                ->get();
        }

        // 2. Tarik Master Akun (Bagan Akun berawalan 4 sampai 9)
        $masterAccounts = Account::whereIn(DB::raw('LEFT(TRIM(account_code), 1)'), ['4', '5', '6', '7', '8', '9'])
            ->get()
            ->keyBy(function($item) {
                return trim($item->account_code);
            });

        // 3. Penggabungan Daftar Kode Akun
        $dataCodes = $data->pluck('account_code')->unique();
        $allCodes  = collect($masterAccounts->keys())->merge($dataCodes)->unique();

        $accountsToProcess = [];
        foreach ($allCodes as $code) {
            $prefix = substr($code, 0, 1);
            if (in_array($prefix, ['4', '5', '6', '7', '8', '9'])) {
                $masterAcc = $masterAccounts->get($code);
                $accountsToProcess[] = (object) [
                    'account_code'   => $code,
                    'account_name'   => $masterAcc ? $masterAcc->account_name : 'Akun Tidak Dikenal (Belum Didaftarkan)',
                    'normal_balance' => $masterAcc ? $masterAcc->normal_balance : null,
                ];
            }
        }

        usort($accountsToProcess, function($a, $b) {
            return $a->account_code <=> $b->account_code;
        });

        // 4. Bangun Matriks Laporan
        if ($tab == 'bulanan') {
            $report = $this->generateMatrixStructure($accountsToProcess, $data, $periods);
        } else {
            $report = $this->generateStructure($accountsToProcess, $data);
        }

        $lastSync = JournalHeader::max('created_at');

        $company = \App\Models\CompanyProfile::first();

        // 5. Render Antarmuka atau Ekspor
        if ($tab == 'bulanan') {
            $view = view('report.profit-loss', compact('tab', 'report', 'year', 'interval', 'month', 'periods', 'isExport', 'company', 'lastSync'));
            if ($isExport) {
                return response($view)->header('Content-Type', 'application/vnd.ms-excel')
                                      ->header('Content-Disposition', 'attachment; filename="Laba_Rugi_'.($interval == 'harian' ? 'Harian' : 'Tahun').'_'.$year.'.xls"');
            }
            return $view;
        } else {
            $view = view('report.profit-loss', compact('tab', 'report', 'startDate', 'endDate', 'isExport', 'company', 'lastSync'));
            if ($isExport) {
                return response($view)->header('Content-Type', 'application/vnd.ms-excel')
                                      ->header('Content-Disposition', 'attachment; filename="Laba_Rugi_'.$startDate.'_sd_'.$endDate.'.xls"');
            }
            return $view;
        }
    }

    private function generateStructure($accounts, $data)
    {
        $structure = [
            'pendapatan'      => ['title' => 'Pendapatan', 'items' => [], 'grand_total' => 0],
            'hpp'             => ['title' => 'Beban Pokok Penjualan (HPP)', 'items' => [], 'grand_total' => 0],
            'biaya'           => ['title' => 'Beban Operasional', 'items' => [], 'grand_total' => 0],
            'pendapatan_lain' => ['title' => 'Pendapatan Lainnya', 'items' => [], 'grand_total' => 0],
            'beban_lain'      => ['title' => 'Beban Lain-lain', 'items' => [], 'grand_total' => 0],
        ];

        $fastData = [];
        foreach ($data as $item) {
            $fastData[$item->account_code][$item->position] = $item->total;
        }

        foreach ($accounts as $acc) {
            $code     = trim($acc->account_code);
            $prefix   = substr($code, 0, 1);
            
            $groupInfo = AccountClassifier::determineGroup($prefix, $acc->normal_balance ?? null);
            $group     = $groupInfo['group'];
            $isKredit  = $groupInfo['isKredit'];

            $debet  = $fastData[$code]['DEBET'] ?? 0;
            $kredit = $fastData[$code]['KREDIT'] ?? 0;
            
            $balance      = $isKredit ? ($kredit - $debet) : ($debet - $kredit);
            $accountTotal = round($balance, 2);
            
            if ($accountTotal != 0) {
                $structure[$group]['items'][] = [
                    'code'    => $code,
                    'name'    => $acc->account_name,
                    'balance' => $accountTotal
                ];

                // PERBAIKAN: Logika Pengurang Akun Kontra (Contra-Account)
                // Jika arah saldo akun (isKredit) sejajar dengan arah saldo normal grup,
                // maka akun ini searah — tambahkan. Jika berlawanan, akun ini adalah
                // akun kontra (misal: Diskon Penjualan, Retur Penjualan) — kurangi.
                $groupNormalIsKredit = in_array($group, ['pendapatan', 'pendapatan_lain']);

                if ($isKredit === $groupNormalIsKredit) {
                    $structure[$group]['grand_total'] += $accountTotal;
                } else {
                    $structure[$group]['grand_total'] -= $accountTotal;
                }

                $structure[$group]['grand_total'] = round($structure[$group]['grand_total'], 2);
            }
        }

        return $structure;
    }

    private function generateMatrixStructure($accounts, $data, array $periods)
    {
        $periodKeys = array_column($periods, 'key');
        $emptyTotals = array_fill_keys($periodKeys, 0);

        $structure = [
            'pendapatan'      => ['title' => 'Pendapatan', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            'hpp'             => ['title' => 'Beban Pokok Penjualan (HPP)', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            'biaya'           => ['title' => 'Beban Operasional', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            'pendapatan_lain' => ['title' => 'Pendapatan Lainnya', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            'beban_lain'      => ['title' => 'Beban Lain-lain', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
        ];

        $fastData = [];
        foreach ($data as $item) {
            $fastData[$item->account_code][(string) $item->period_key][$item->position] = $item->total;
        }

        foreach ($accounts as $acc) {
            $code        = trim($acc->account_code);
            $prefix      = substr($code, 0, 1);
            
            $groupInfo = AccountClassifier::determineGroup($prefix, $acc->normal_balance ?? null);
            $group     = $groupInfo['group'];
            $isKredit  = $groupInfo['isKredit'];

            $periodVals   = [];
            $accountTotal = 0;
            $hasData      = false;

            foreach ($periodKeys as $pk) {
                $debet  = $fastData[$code][$pk]['DEBET'] ?? 0;
                $kredit = $fastData[$code][$pk]['KREDIT'] ?? 0;

                $balance = $isKredit ? ($kredit - $debet) : ($debet - $kredit);
                $balanceRounded = round($balance, 2);

                if ($balanceRounded != 0) $hasData = true;

                $periodVals[$pk] = $balanceRounded;
                $accountTotal   += $balanceRounded;
            }

            if ($hasData) {
                $structure[$group]['items'][] = [
                    'code'    => $code,
                    'name'    => $acc->account_name,
                    'periods' => $periodVals,
                    'total'   => round($accountTotal, 2),
                ];

                // PERBAIKAN: Logika Pengurang Akun Kontra (Contra-Account) untuk Matriks
                // Jika arah saldo akun (isKredit) sejajar dengan arah saldo normal grup,
                // maka akun ini searah — tambahkan. Jika berlawanan, akun ini adalah
                // akun kontra (misal: Diskon Penjualan, Retur Penjualan) — kurangi.
                $groupNormalIsKredit = in_array($group, ['pendapatan', 'pendapatan_lain']);

                foreach ($periodKeys as $pk) {
                    if ($isKredit === $groupNormalIsKredit) {
                        $structure[$group]['totals'][$pk] += $periodVals[$pk];
                    } else {
                        $structure[$group]['totals'][$pk] -= $periodVals[$pk];
                    }
                    $structure[$group]['totals'][$pk] = round($structure[$group]['totals'][$pk], 2);
                }

                if ($isKredit === $groupNormalIsKredit) {
                    $structure[$group]['grand_total'] += $accountTotal;
                } else {
                    $structure[$group]['grand_total'] -= $accountTotal;
                }
                $structure[$group]['grand_total'] = round($structure[$group]['grand_total'], 2);
            }
        }

        return $structure;
    }

    private function calculateUltimateProfit($startDate, $endDate) {
        $data = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select('position', DB::raw('SUM(amount) as total'))
                ->where('transaction_date', '>=', $startDate . ' 00:00:00')
                ->where('transaction_date', '<=', $endDate . ' 23:59:59')
                ->whereIn(DB::raw('LEFT(TRIM(account_code), 1)'), ['4','5','6','7','8','9'])
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
                ->where(function ($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy('position')
                ->get();
        
        $totalLabaRugi = 0;
        foreach($data as $row) {
            $amount = round($row->total, 2);
            if ($row->position == 'KREDIT') {
                $totalLabaRugi += $amount; 
            } else {
                $totalLabaRugi -= $amount; 
            }
        }
        return round($totalLabaRugi, 2);
    }
}