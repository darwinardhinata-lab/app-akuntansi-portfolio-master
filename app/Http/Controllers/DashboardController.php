<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Asset;
use App\Models\HelperCode;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $year = (int) date('Y');
        $today = Carbon::now();

        $kpi = $this->buildKpi($year);
        $chart = $this->buildMonthlyChart($year);
        $payments = $this->latestPayments();
        $journals = JournalHeader::with(['details.account'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
        $assets = $this->latestAssetsWithDepreciation($today);
        $stats = $this->buildSystemStats();

        return view('dashboard', compact(
            'year',
            'today',
            'kpi',
            'chart',
            'payments',
            'journals',
            'assets',
            'stats'
        ));
    }

    public function getChartData(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $interval = $request->input('interval', 'bulanan');

        // 1. Ambil Kueri Arus Kas (Hanya Akun Kas & Bank)
        $cashAccountCodes = DB::table('accounts')
            ->where(function($q) {
                $q->where('coa_type', 'like', '%Cash%')
                  ->orWhere('coa_type', 'like', '%Bank%')
                  ->orWhere('account_name', 'like', '%Kas%')
                  ->orWhere('account_name', 'like', '%Bank%');
            })->where(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), '1')
            ->pluck('account_code')->map(fn($c) => trim($c))->toArray();

        if (empty($cashAccountCodes)) $cashAccountCodes = ['00000'];

        $monthExpr = $this->getMonthExpression();

        $cashRows = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw("SUM(CASE WHEN journal_details.position = 'DEBET' THEN journal_details.amount ELSE 0 END) as cash_in")
            ->selectRaw("SUM(CASE WHEN journal_details.position = 'KREDIT' THEN journal_details.amount ELSE 0 END) as cash_out")
            ->whereIn(DB::raw('TRIM(journal_details.account_code)'), $cashAccountCodes)
            ->whereYear('journal_headers.transaction_date', $year)
            ->where('journal_headers.evidence_number', '!=', 'SA-00000')
            ->groupBy(DB::raw($monthExpr))
            ->get()->keyBy('month');

        // 2. Ambil Kueri Laba Rugi Matriks
        $plRows = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw("SUM(CASE WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('4','7') AND journal_details.position = 'KREDIT' THEN journal_details.amount WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('4','7') AND journal_details.position = 'DEBET' THEN -journal_details.amount ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('5','6','8','9') AND journal_details.position = 'DEBET' THEN journal_details.amount WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('5','6','8','9') AND journal_details.position = 'KREDIT' THEN -journal_details.amount ELSE 0 END) as expense")
            ->whereYear('journal_headers.transaction_date', $year)
            ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
            ->where(function ($q) {
                $q->whereNull('journal_headers.is_opening_balance')
                  ->orWhere('journal_headers.is_opening_balance', 0);
            })
            ->groupBy(DB::raw($monthExpr))
            ->get()->keyBy('month');

        // 3. Format Data Berdasarkan Interval
        $labels = []; $cashIn = []; $cashOut = []; $netCash = []; $revenue = []; $expense = []; $netProfit = [];
        
        if ($interval == 'kuartal') {
            $labels = ['Q1 (Jan-Mar)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Okt-Des)'];
            for ($q = 1; $q <= 4; $q++) {
                $qCashIn = 0; $qCashOut = 0; $qRev = 0; $qExp = 0;
                $startMonth = ($q - 1) * 3 + 1;
                for ($m = $startMonth; $m <= $startMonth + 2; $m++) {
                    $qCashIn += $cashRows->has($m) ? (float)$cashRows->get($m)->cash_in : 0;
                    $qCashOut += $cashRows->has($m) ? (float)$cashRows->get($m)->cash_out : 0;
                    $qRev += $plRows->has($m) ? (float)$plRows->get($m)->revenue : 0;
                    $qExp += $plRows->has($m) ? (float)$plRows->get($m)->expense : 0;
                }
                $cashIn[] = round($qCashIn / 1_000_000, 2);
                $cashOut[] = round($qCashOut / 1_000_000, 2);
                $netCash[] = round(($qCashIn - $qCashOut) / 1_000_000, 2);
                $revenue[] = round($qRev / 1_000_000, 2);
                $expense[] = round($qExp / 1_000_000, 2);
                $netProfit[] = round(($qRev - $qExp) / 1_000_000, 2);
            }
        } else {
            // Default: Bulanan
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            for ($m = 1; $m <= 12; $m++) {
                $cRow = $cashRows->get($m); $pRow = $plRows->get($m);
                $in = $cRow ? (float)$cRow->cash_in : 0;
                $out = $cRow ? (float)$cRow->cash_out : 0;
                $cashIn[] = round($in / 1_000_000, 2);
                $cashOut[] = round($out / 1_000_000, 2);
                $netCash[] = round(($in - $out) / 1_000_000, 2);

                $rev = $pRow ? (float)$pRow->revenue : 0;
                $exp = $pRow ? (float)$pRow->expense : 0;
                $revenue[] = round($rev / 1_000_000, 2);
                $expense[] = round($exp / 1_000_000, 2);
                $netProfit[] = round(($rev - $exp) / 1_000_000, 2);
            }
        }

        return response()->json([
            'labels' => $labels,
            'arusKas' => ['in' => $cashIn, 'out' => $cashOut, 'net' => $netCash],
            'labaRugi' => ['revenue' => $revenue, 'expense' => $expense, 'net' => $netProfit]
        ]);
    }

    private function buildKpi(int $year): array
    {
        $base = JournalDetail::query()
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->whereYear('journal_headers.transaction_date', $year)
            ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
            ->where(function ($q) {
                $q->whereNull('journal_headers.is_opening_balance')
                  ->orWhere('journal_headers.is_opening_balance', 0);
            });

        $pemasukan = (clone $base)
            ->whereIn(DB::raw('SUBSTR(TRIM(journal_details.account_code), 1, 1)'), ['4', '7'])
            ->where('journal_details.position', 'KREDIT')
            ->sum('journal_details.amount');

        $pengeluaran = (clone $base)
            ->whereIn(DB::raw('SUBSTR(TRIM(journal_details.account_code), 1, 1)'), ['5', '6', '8'])
            ->where('journal_details.position', 'DEBET')
            ->sum('journal_details.amount');

        $ppPending = DB::table('transaksi_payment_plan')
            ->where('status_payment', 'PENGAJUAN')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(nominal), 0) as total')
            ->first();

        return [
            'pemasukan'        => (float) $pemasukan,
            'pengeluaran'      => (float) $pengeluaran,
            'journal_count'    => JournalHeader::whereYear('transaction_date', $year)->count(),
            'pp_pending_count' => (int) ($ppPending->cnt ?? 0),
            'pp_pending_sum'   => (float) ($ppPending->total ?? 0),
        ];
    }

    private function buildMonthlyChart(int $year): array
    {
        // 1. HITUNG ARUS KAS AKTUAL BERDASARKAN KAS & BANK (COA AKUN KAS)
        $cashAccountCodes = DB::table('accounts')
            ->where(function($q) {
                $q->where('coa_type', 'like', '%Cash%')
                  ->orWhere('coa_type', 'like', '%Bank%')
                  ->orWhere('account_name', 'like', '%Kas%')
                  ->orWhere('account_name', 'like', '%Bank%');
            })->where(DB::raw('SUBSTR(TRIM(account_code), 1, 1)'), '1')
            ->pluck('account_code')->map(fn($c) => trim($c))->toArray();

        if (empty($cashAccountCodes)) $cashAccountCodes = ['00000'];

        $monthExpr = $this->getMonthExpression();

        $cashRows = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw("SUM(CASE WHEN journal_details.position = 'DEBET' THEN journal_details.amount ELSE 0 END) as cash_in")
            ->selectRaw("SUM(CASE WHEN journal_details.position = 'KREDIT' THEN journal_details.amount ELSE 0 END) as cash_out")
            ->whereIn(DB::raw('TRIM(journal_details.account_code)'), $cashAccountCodes)
            ->whereYear('journal_headers.transaction_date', $year)
            ->where('journal_headers.evidence_number', '!=', 'SA-00000')
            ->groupBy(DB::raw($monthExpr))
            ->get()
            ->keyBy('month');

        // 2. HITUNG LABA RUGI MATRIKS AKTUAL (REVENUE VS EXPENSE)
        $plRows = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->selectRaw("{$monthExpr} as month")
            ->selectRaw("SUM(CASE WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('4','7') AND journal_details.position = 'KREDIT' THEN journal_details.amount WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('4','7') AND journal_details.position = 'DEBET' THEN -journal_details.amount ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('5','6','8','9') AND journal_details.position = 'DEBET' THEN journal_details.amount WHEN SUBSTR(TRIM(journal_details.account_code), 1, 1) IN ('5','6','8','9') AND journal_details.position = 'KREDIT' THEN -journal_details.amount ELSE 0 END) as expense")
            ->whereYear('journal_headers.transaction_date', $year)
            ->where('journal_headers.evidence_number', 'NOT LIKE', 'SA-%')
            ->where(function ($q) {
                $q->whereNull('journal_headers.is_opening_balance')
                  ->orWhere('journal_headers.is_opening_balance', 0);
            })
            ->groupBy(DB::raw($monthExpr))
            ->get()
            ->keyBy('month');

        $cashIn = []; $cashOut = []; $netCash = [];
        $revenue = []; $expense = []; $netProfit = [];

        for ($m = 1; $m <= 12; $m++) {
            $cRow = $cashRows->get($m);
            $pRow = $plRows->get($m);

            $in = $cRow ? (float)$cRow->cash_in : 0;
            $out = $cRow ? (float)$cRow->cash_out : 0;
            $cashIn[] = round($in / 1_000_000, 2);
            $cashOut[] = round($out / 1_000_000, 2);
            $netCash[] = round(($in - $out) / 1_000_000, 2); // Nilai minus akan alami tersimpan sebagai minus

            $rev = $pRow ? (float)$pRow->revenue : 0;
            $exp = $pRow ? (float)$pRow->expense : 0;
            $revenue[] = round($rev / 1_000_000, 2);
            $expense[] = round($exp / 1_000_000, 2);
            $netProfit[] = round(($rev - $exp) / 1_000_000, 2);
        }

        return [
            'cashIn' => $cashIn, 'cashOut' => $cashOut, 'netCash' => $netCash,
            'revenue' => $revenue, 'expense' => $expense, 'netProfit' => $netProfit
        ];
    }

    private function latestPayments()
    {
        return DB::table('transaksi_payment_plan')
            ->join('master_divisi', 'transaksi_payment_plan.id_divisi', '=', 'master_divisi.id_divisi')
            ->select('transaksi_payment_plan.*', 'master_divisi.nama_divisi')
            ->orderByDesc('transaksi_payment_plan.tgl_pengajuan')
            ->limit(5)
            ->get();
    }

    private function latestAssetsWithDepreciation(Carbon $today)
    {
        $assets = Asset::orderByDesc('purchase_date')->limit(5)->get();

        foreach ($assets as $asset) {
            $asset->depreciation_pct = 0;

            if ($asset->useful_life_months > 0) {
                $age = min(
                    Carbon::parse($asset->purchase_date)->diffInMonths($today),
                    $asset->useful_life_months
                );
                $asset->depreciation_pct = (int) round($age / $asset->useful_life_months * 100);
            }
        }

        return $assets;
    }

    private function buildSystemStats(): array
    {
        return [
            'total_akun'   => Account::count(),
            'total_helper' => HelperCode::count(),
            'total_divisi' => DB::table('master_divisi')->where('status_aktif', 1)->count(),
            'total_aset'   => Asset::count(),
            'total_jurnal' => JournalHeader::count(),
            'total_pp'     => DB::table('transaksi_payment_plan')->count(),
        ];
    }

    private function getMonthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', journal_headers.transaction_date) AS INTEGER)"
            : "MONTH(journal_headers.transaction_date)";
    }
}