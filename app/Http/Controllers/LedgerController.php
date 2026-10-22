<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Support\JournalDocumentLinkResolver;
use Illuminate\Support\Facades\DB;

use App\Exports\LedgerExport;
use Maatwebsite\Excel\Facades\Excel;

class LedgerController extends Controller
{
    public function export(Request $request)
    {
        $accounts  = Account::with('translations')->orderBy('account_code', 'asc')->get();
        $accountCodes = \App\Support\AccountFilter::codes($request);
        $accountCodes = $accountCodes ?: array_filter([$accounts->first()->account_code ?? '']);
        $accCode = count($accountCodes) === 1 ? $accountCodes[0] : 'Multi_Akun';
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate   = $request->get('end_date', date('Y-m-t'));

        $filename = 'Buku_Besar_' . ($accCode ?: 'Akun') . '_' . $startDate . '_sd_' . $endDate . '.xlsx';

        $export = count($accountCodes) > 1
            ? new \App\Exports\MultiAccountLedgerExport($accountCodes, $startDate, $endDate)
            : new LedgerExport($accCode, $startDate, $endDate);

        return Excel::download($export, $filename);
    }

    public function index(Request $request)
    {
        $accountCodes = \App\Support\AccountFilter::codes($request);
        $accounts = Account::with('translations')->orderBy('account_code')->get();
        $accountCodes = $accountCodes ?: array_filter([$accounts->first()->account_code ?? '']);
        $sections = [];
        foreach ($accountCodes ?: [''] as $code) {
            $sections[] = $this->accountData($request, $accounts, $code, $accountCodes);
        }

        return view('ledger.index', array_merge($sections[0], compact('sections', 'accountCodes')));
    }
        
    private function accountData(Request $request, $accounts, string $accCode, array $accountCodes): array
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate   = $request->get('end_date', date('Y-m-t'));
        $perPage   = $request->get('per_page', 50);
        $pageName = count($accountCodes) > 1 ? 'page_'.$accCode : 'page';
        $page = max(1, (int) $request->get($pageName, 1));

        // 1. Hitung Saldo Awal Murni (Sebelum rentang start_date)
        $openingDebet  = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->where('account_code', $accCode)
            ->where('position', 'DEBET')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $openingKredit = JournalDetail::join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->where('account_code', $accCode)
            ->where('position', 'KREDIT')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $selectedAccount = $accounts->firstWhere('account_code', $accCode);
        $prefix = substr(trim($accCode), 0, 1);

        // PERBAIKAN: Prioritaskan normal_balance dari Master COA (bukan hanya
        // prefix kode akun). Ini menangani akun kontra (contra-account) seperti
        // '44001' (Diskon Penjualan) atau '44010' (Retur Penjualan) yang punya
        // prefix '4' tapi normal_balance = 'DEBET' di master data.
        // Fallback ke heuristik prefix HANYA jika akun belum terdaftar di master
        // atau kolom normal_balance kosong.
        if ($selectedAccount && !empty($selectedAccount->normal_balance)) {
            $isDebetNormal = strtoupper(trim($selectedAccount->normal_balance)) === 'DEBET';
        } else {
            $isDebetNormal = in_array($prefix, ['1', '5', '6', '9']);
        }

        $baseOpeningBalance = $isDebetNormal ? ($openingDebet - $openingKredit) : ($openingKredit - $openingDebet);
        $baseOpeningBalance = round($baseOpeningBalance, 2);

        // =====================================================================================
        // 2. RUMUS SALDO PINDAHAN AMAN MEMORI (Menghitung akumulasi baris yang terlewati)
        // =====================================================================================
        $runningBalance = $baseOpeningBalance;

        if ($page > 1) {
            $skippedCount = ($page - 1) * $perPage;
            
            // Hanya menarik posisi dan nominal dari baris sebelumnya (beban memori nyaris 0 KB)
            $skippedData = \App\Support\ProtectedJournalQuery::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->select('position', 'amount')
                ->where('account_code', $accCode)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'asc')
                ->orderBy('journal_details.id', 'asc')
                ->limit($skippedCount)
                ->get();

            $skipDeb = $skippedData->where('position', 'DEBET')->sum('amount');
            $skipKre = $skippedData->where('position', 'KREDIT')->sum('amount');

            if ($isDebetNormal) {
                $runningBalance += ($skipDeb - $skipKre);
            } else {
                $runningBalance += ($skipKre - $skipDeb);
            }
        }

        $pageOpeningBalance = round($runningBalance, 2);

        // =====================================================================================
        // 3. TARIK DATA DENGAN PAGINASI (Mencegah Fatal Error 512MB)
        // =====================================================================================
        $transactions = JournalDetail::with(config('libretranslate.enabled')
            ? ['header.details.account.translations', 'header.noteTranslations'] : ['header.details.account.translations'])
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->select('journal_details.*', 'journal_headers.transaction_date', 'journal_headers.evidence_number', 'journal_headers.source_doc_no', 'journal_headers.notes', 'journal_headers.journal_type')
            ->where('account_code', $accCode)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('journal_details.id', 'asc')
            ->paginate($perPage, ['*'], $pageName, $page)
            ->appends(array_merge($request->except($pageName), [
                'account_codes' => $accountCodes,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'per_page'     => $perPage,
            ]));

        JournalDocumentLinkResolver::attach($transactions->getCollection()->pluck('header')->filter());

        return compact(
            'accounts', 'accCode', 'startDate', 'endDate', 
            'pageOpeningBalance', 'transactions', 'selectedAccount', 
            'isDebetNormal', 'perPage', 'page'
        );
    }
}