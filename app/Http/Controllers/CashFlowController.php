<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalDetail;
use Illuminate\Support\Facades\DB;
use App\Support\ReportInterval;

class CashFlowController extends Controller
{
    public function index(Request $request)
    {
        $tab      = $request->get('tab', 'direct');
        $isExport = $request->get('export') == 'excel';

        $year     = (int) $request->get('year', date('Y'));
        $interval = $request->get('interval', 'bulanan');
        if (!ReportInterval::isValid($interval)) $interval = 'bulanan';
        $month    = (int) $request->get('month', date('n'));

        // Menentukan rentang tanggal laporan secara dinamis
        [$rangeStart, $rangeEnd] = ReportInterval::range($interval, $year, $month);
        $periodExpr = ReportInterval::periodKeyExpr($interval, 'transaction_date');
        $periods    = ReportInterval::buildPeriods($interval, $year, $month);
        $periodKeys = array_column($periods, 'key');

        // 1. Ambil daftar Akun Kategori Kas & Bank (FIXED REGEX - CEGAH AKUN 'KASUR/KASBON')
        $cashAccounts = Account::where(function($q) {
            $q->where('coa_type', 'like', '%Cash%')
              ->orWhere('coa_type', 'like', '%Bank%')
              ->orWhere('account_name', 'like', 'Kas %')
              ->orWhere('account_name', 'like', '% Kas')
              ->orWhere('account_name', '=', 'Kas')
              ->orWhere('account_name', 'like', 'Bank %')
              ->orWhere('account_name', 'like', '% Bank')
              ->orWhere('account_name', 'like', '%Shopee%')
              ->orWhere('account_name', 'like', '%Tiktok%')
              ->orWhere('account_name', 'like', '%Tokopedia%')
              ->orWhere('account_name', 'like', '%Lazada%')
              ->orWhere('account_name', 'like', '%Dummy%');
        })->where(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), '1')
          ->orderBy('account_code', 'asc')
          ->get();

        $cashAccountCodes = $cashAccounts->pluck('account_code')->map(fn($c) => trim($c))->toArray();
        if (empty($cashAccountCodes)) $cashAccountCodes = ['00000']; 

        // ====================================================================================
        // 2. SALDO AWAL KAS & BANK (FIXED DYNAMIC LOGIC MENCEGAH KEBOCORAN)
        // ====================================================================================
            $saldoAwalTahun = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->whereIn(DB::raw('TRIM(journal_details.account_code)'), $cashAccountCodes)
            ->where(function($query) use ($rangeStart) {
                // a. Mutasi Kas Normal SEBELUM tanggal mulai laporan (Mengabaikan Opening Balance)
                $query->where(function($q1) use ($rangeStart) {
                    $q1->where('journal_headers.transaction_date', '<', $rangeStart . ' 00:00:00')
                       ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                       ->where(function($q) {
                            $q->whereNull('journal_headers.is_opening_balance')
                              ->orWhere('journal_headers.is_opening_balance', 0);
                        });
                })
                // b. ATAU Ambil seluruh Jurnal Saldo Awal
                ->orWhere(function($q2) use ($rangeStart) {
                    $q2->where('journal_headers.transaction_date', '<=', $rangeStart . ' 23:59:59')
                       ->where(function($q) {
                            $q->where('journal_headers.notes', 'LIKE', '%SETUP SALDO AWAL%')
                              ->orWhere('journal_headers.is_opening_balance', 1);
                        });
                });
            })
            ->selectRaw('SUM(CASE WHEN journal_details.position = "DEBET" THEN journal_details.amount ELSE -journal_details.amount END) as net_balance')
            ->value('net_balance') ?? 0;

        // ====================================================================================
        // METODE LANGSUNG (DIRECT METHOD)
        // ====================================================================================
        if ($tab == 'direct') {
            $emptyTotals = array_fill_keys($periodKeys, 0);
            $report = [
                'inflows'  => ['title' => 'Arus Kas Masuk (Penerimaan Kas)', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
                'outflows' => ['title' => 'Arus Kas Keluar (Pengeluaran Kas)', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            ];

            $cashData = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(
                    DB::raw('TRIM(journal_details.account_code) as account_code'),
                    'journal_details.position',
                    DB::raw($periodExpr . ' as period_key'),
                    DB::raw('SUM(journal_details.amount) as total')
                )
                ->whereIn(DB::raw('TRIM(journal_details.account_code)'), $cashAccountCodes)
                ->where('journal_headers.transaction_date', '>=', $rangeStart . ' 00:00:00')
                ->where('journal_headers.transaction_date', '<=', $rangeEnd . ' 23:59:59')
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                // BLOKIR JURNAL SALDO AWAL MASUK KE MUTASI JULI
                ->where(function($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy(DB::raw('TRIM(journal_details.account_code)'), 'journal_details.position', DB::raw($periodExpr))
                ->get();

            $fastCash = [];
            foreach ($cashData as $row) {
                $fastCash[$row->account_code][$row->position][(string) $row->period_key] = (float) $row->total;
            }

            foreach ($cashAccounts as $acc) {
                $code = trim($acc->account_code);
                $periodVals = []; $debTotal = 0; $kreTotal = 0;

                foreach ($periodKeys as $pk) {
                    $d = $fastCash[$code]['DEBET'][$pk] ?? 0;
                    $k = $fastCash[$code]['KREDIT'][$pk] ?? 0;
                    $periodVals[$pk] = ['debet' => $d, 'kredit' => $k];
                    $debTotal += $d; $kreTotal += $k;
                }

                if (round($debTotal, 2) > 0) {
                    $item = ['code' => $code, 'name' => 'Penerimaan: '.$acc->account_name, 'periods' => [], 'total' => $debTotal];
                    foreach ($periodKeys as $pk) { $item['periods'][$pk] = $periodVals[$pk]['debet']; $report['inflows']['totals'][$pk] += $periodVals[$pk]['debet']; }
                    $report['inflows']['items'][] = $item;
                    $report['inflows']['grand_total'] += $debTotal;
                }
                if (round($kreTotal, 2) > 0) {
                    $item = ['code' => $code, 'name' => 'Pengeluaran: '.$acc->account_name, 'periods' => [], 'total' => $kreTotal];
                    foreach ($periodKeys as $pk) { $item['periods'][$pk] = $periodVals[$pk]['kredit']; $report['outflows']['totals'][$pk] += $periodVals[$pk]['kredit']; }
                    $report['outflows']['items'][] = $item;
                    $report['outflows']['grand_total'] += $kreTotal;
                }
            }
        }
        // ====================================================================================
        // METODE TIDAK LANGSUNG (INDIRECT METHOD)
        // ====================================================================================
        else {
            $emptyTotals = array_fill_keys($periodKeys, 0);
            $report = [
                'operasi'   => ['title' => 'I. Arus Kas dari Aktivitas Operasi', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
                'investasi' => ['title' => 'II. Arus Kas dari Aktivitas Investasi', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
                'pendanaan' => ['title' => 'III. Arus Kas dari Aktivitas Pendanaan', 'items' => [], 'totals' => $emptyTotals, 'grand_total' => 0],
            ];

            $allData = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select(
                    DB::raw('TRIM(journal_details.account_code) as account_code'),
                    'journal_details.position',
                    DB::raw($periodExpr . ' as period_key'),
                    DB::raw('SUM(journal_details.amount) as total')
                )
                ->where('journal_headers.transaction_date', '>=', $rangeStart . ' 00:00:00')
                ->where('journal_headers.transaction_date', '<=', $rangeEnd . ' 23:59:59')
                ->where('journal_headers.notes', 'NOT LIKE', '%SETUP SALDO AWAL%')
                // BLOKIR JURNAL SALDO AWAL MASUK KE MUTASI JULI
                ->where(function($q) {
                    $q->whereNull('journal_headers.is_opening_balance')
                      ->orWhere('journal_headers.is_opening_balance', 0);
                })
                ->groupBy(DB::raw('TRIM(journal_details.account_code)'), 'journal_details.position', DB::raw($periodExpr))
                ->get();

            $uniqueCodes = $allData->pluck('account_code')->unique()->toArray();
            $accountsMap = Account::whereIn('account_code', $uniqueCodes)->get()->keyBy(fn($c) => trim($c->account_code));

            $netIncomePeriods = array_fill_keys($periodKeys, 0);
            $neracaPeriods = [];

            foreach ($allData as $row) {
                $code = trim($row->account_code);
                if (in_array($code, $cashAccountCodes)) continue; 

                $pk = (string) $row->period_key;
                $prefix = substr($code, 0, 1);
                
                $val = (strtoupper(trim($row->position)) === 'KREDIT') ? (float) $row->total : -((float) $row->total);

                if (in_array($prefix, ['4', '5', '6', '7', '8', '9'])) {
                    $netIncomePeriods[$pk] += $val;
                } else {
                    if (!isset($neracaPeriods[$code])) {
                        $neracaPeriods[$code] = array_fill_keys($periodKeys, 0);
                    }
                    $neracaPeriods[$code][$pk] += $val;
                }
            }

            $report['operasi']['items'][] = [
                'code' => '', 'name' => 'Laba (Rugi) Bersih Terkonsolidasi', 'periods' => $netIncomePeriods,
                'total' => array_sum($netIncomePeriods), 'is_bold' => true
            ];

            ksort($neracaPeriods);
            foreach ($neracaPeriods as $code => $periodVals) {
                $accTotal = array_sum($periodVals);
                if (round(abs($accTotal), 2) > 0) {
                    $prefix = substr($code, 0, 1);
                    $prefix2 = substr($code, 0, 2);
                    $accName = $accountsMap->has($code) ? $accountsMap[$code]->account_name : 'Akun Tidak Dikenal';
                    
                                        // H3 FIX: 22xxxx (Hutang Usaha) masuk aktivitas operasional, bukan pendanaan
                    $section = ($prefix2 == '11' || $prefix2 == '21' || $prefix2 == '22') ? 'operasi' : (($prefix == '1' || $prefix2 == '12') ? 'investasi' : 'pendanaan');
                    if (str_contains(strtolower($accName), 'penyusutan')) $section = 'operasi';
                    
                    $report[$section]['items'][] = [
                        'code' => $code, 
                        'name' => 'Perubahan ' . $accName, 
                        'periods' => $periodVals, 
                        'total' => $accTotal, 
                        'is_bold' => false
                    ];
                }
            }

            foreach (['operasi', 'investasi', 'pendanaan'] as $sec) {
                foreach ($report[$sec]['items'] as $item) {
                    foreach ($periodKeys as $pk) $report[$sec]['totals'][$pk] += $item['periods'][$pk];
                    $report[$sec]['grand_total'] += $item['total'];
                }
            }
        }

        $view = view('report.cash-flow', compact('tab', 'report', 'year', 'interval', 'month', 'periods', 'saldoAwalTahun', 'isExport'));
        if ($isExport) {
            return response($view)->header('Content-Type', 'application/vnd.ms-excel')
                ->header('Content-Disposition', 'attachment; filename="Arus_Kas_' . strtoupper($tab) . '_' . $year . '.xls"');
        }
        return $view;
    }
}
