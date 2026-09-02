<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AdvancedReportController extends Controller
{
    // ==========================================
    // 1. LAPORAN KRONOLOGI HPP PER BARANG (KARTU STOK)
    // ==========================================
    public function cogsChronology(Request $request)
    {
        $products = Product::orderBy('name', 'asc')->get();
        $selectedProductId = $request->get('product_id');
        $ledgers = [];
        $product = null;

        if ($selectedProductId) {
            $product = Product::find($selectedProductId);
            // Mengambil riwayat mutasi dari tabel inventory_ledgers
            $ledgers = DB::table('inventory_ledgers')
                ->where('product_id', $selectedProductId)
                ->orderBy('transaction_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        return view('reports.cogs_chronology', compact('products', 'ledgers', 'product'));
    }

    // ==========================================
    // 2. LAPORAN PER TAG / PROYEK
    // ==========================================
    public function reportByTag(Request $request)
    {
        $tag = $request->get('tag');
        $transactions = collect();

        if ($tag) {
            $transactions = DB::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->leftJoin('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
                ->select(
                    'journal_headers.transaction_date', 
                    'journal_headers.evidence_number', 
                    'journal_headers.description as header_desc', 
                    'journal_headers.tags',
                    'accounts.account_name',
                    DB::raw('CASE WHEN journal_details.position = "DEBET" THEN journal_details.amount ELSE 0 END as debit'),
                    DB::raw('CASE WHEN journal_details.position = "KREDIT" THEN journal_details.amount ELSE 0 END as credit')
                )
                // FIX: Filter diarahkan ke kolom 'tags' ATAU 'description'
                ->where(function($q) use ($tag) {
                    $q->where('journal_headers.tags', 'LIKE', "%{$tag}%")
                      ->orWhere('journal_headers.description', 'LIKE', "%{$tag}%");
                })
                ->orderBy('journal_headers.transaction_date', 'desc')
                ->get();
        }

        return view('reports.tag_report', compact('transactions', 'tag'));
    }

    // ==========================================
    // 3. LAPORAN PIUTANG & UANG MUKA PENJUALAN
    // ==========================================
    public function reportArDp()
    {
       // Menggunakan akun: Piutang Usaha
        $kodeAkunPiutang = [config('coa.piutang_usaha')]; 
        
        // Menggunakan akun: Uang Muka Penjualan / Deposit Pelanggan
        $kodeAkunUangMukaJual = [config('coa.hutang_usaha')];

        // Piutang Bersaldo Normal DEBIT (Debit - Kredit)
        $piutang = DB::table('journal_details')
            ->join('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->select(
                'accounts.account_code', 
                'accounts.account_name', 
                DB::raw('SUM(CASE WHEN journal_details.position = "DEBET" THEN journal_details.amount ELSE -journal_details.amount END) as balance')
            )
            ->whereIn('accounts.account_code', $kodeAkunPiutang)
            ->groupBy('accounts.account_code', 'accounts.account_name')
            ->get();

        // Uang Muka Penjualan Bersaldo Normal KREDIT (Kredit - Debit)
        $uangMuka = DB::table('journal_details')
            ->join('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->select(
                'accounts.account_code', 
                'accounts.account_name', 
                DB::raw('SUM(CASE WHEN journal_details.position = "KREDIT" THEN journal_details.amount ELSE -journal_details.amount END) as balance')
            )
            ->whereIn('accounts.account_code', $kodeAkunUangMukaJual)
            ->groupBy('accounts.account_code', 'accounts.account_name')
            ->get();

        return view('reports.ar_dp_report', compact('piutang', 'uangMuka'));
    }

    // ==========================================
    // 4. LAPORAN HUTANG & UANG MUKA PEMBELIAN
    // ==========================================
    public function reportApDp()
    {
        // Menggunakan akun: Hutang Usaha
        $kodeAkunHutang = [config('coa.hutang_usaha')]; 

        // Menggunakan akun: Uang Muka Pembelian - Persediaan Barang
        $kodeAkunUangMukaBeli = [config('coa.uang_muka_beli')];

        // Hutang Bersaldo Normal KREDIT (Kredit - Debit)
        $hutang = DB::table('journal_details')
            ->join('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->select(
                'accounts.account_code', 
                'accounts.account_name', 
                DB::raw('SUM(CASE WHEN journal_details.position = "KREDIT" THEN journal_details.amount ELSE -journal_details.amount END) as balance')
            )
            ->whereIn('accounts.account_code', $kodeAkunHutang)
            ->groupBy('accounts.account_code', 'accounts.account_name')
            ->get();

        // Uang Muka Pembelian Bersaldo Normal DEBIT (Debit - Kredit)
        $uangMuka = DB::table('journal_details')
            ->join('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->select(
                'accounts.account_code', 
                'accounts.account_name', 
                DB::raw('SUM(CASE WHEN journal_details.position = "DEBET" THEN journal_details.amount ELSE -journal_details.amount END) as balance')
            )
            ->whereIn('accounts.account_code', $kodeAkunUangMukaBeli)
            ->groupBy('accounts.account_code', 'accounts.account_name')
            ->get();

        return view('reports.ap_dp_report', compact('hutang', 'uangMuka'));
    }

    // ==========================================
    // 5. MANAJEMEN PIUTANG PENJUALAN (DENGAN DRILL-DOWN FILTER)
    // ==========================================
    public function arSubledger(Request $request)
    {
        $tab = $request->get('tab', 'tagihan'); 
        $accountCode = $request->get('account_code'); // Tangkap parameter hyperlink
        
        $kodeAkunPiutang = $accountCode ? [$accountCode] : [config('coa.piutang_usaha')]; 
        // Menggabungkan Retur Umum, Shopee, Tiktok
        $kodeAkunReturJual = [config('coa.retur_penjualan'), config('coa.retur_shopee'), config('coa.retur_tiktok')];

        $unpaidInvoices = collect();
        $payments = collect();
        $returns = collect();

        if ($tab === 'tagihan') {
            // 1. Tagihan Belum Lunas (Subquery Optimization)
            $subquery = DB::table('journal_details')
                ->select('journal_id',
                    DB::raw('SUM(CASE WHEN position = "DEBET" THEN amount ELSE -amount END) as remaining_balance'),
                    DB::raw('SUM(CASE WHEN position = "DEBET" THEN amount ELSE 0 END) as total_invoice')
                )
                ->whereIn('account_code', $kodeAkunPiutang)
                ->groupBy('journal_id')
                ->having('remaining_balance', '>', 0);

            $unpaidInvoices = DB::table('journal_headers')
                ->joinSub($subquery, 'details', 'journal_headers.journal_id', '=', 'details.journal_id')
                ->select('journal_headers.evidence_number', 'journal_headers.transaction_date', 'journal_headers.description', 'details.remaining_balance', 'details.total_invoice')
                ->orderBy('journal_headers.transaction_date', 'asc')
                ->simplePaginate(50);
        } elseif ($tab === 'pembayaran') {
            // 2. Riwayat Pembayaran (SimplePaginate Optimization)
            $payments = DB::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->whereIn('journal_details.account_code', $kodeAkunPiutang)
                ->where('journal_details.position', 'KREDIT')
                ->where('journal_headers.description', 'NOT LIKE', '%Retur%')
                ->select('journal_headers.transaction_date', 'journal_headers.evidence_number', 'journal_headers.description', 'journal_details.amount')
                ->orderBy('journal_headers.transaction_date', 'desc')
                ->simplePaginate(50);
        } elseif ($tab === 'retur') {
            // 3. Riwayat Retur Penjualan (SimplePaginate Optimization)
            $returns = DB::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->whereIn('journal_details.account_code', $kodeAkunReturJual)
                ->select('journal_headers.transaction_date', 'journal_headers.evidence_number', 'journal_headers.description', 'journal_details.amount')
                ->orderBy('journal_headers.transaction_date', 'desc')
                ->simplePaginate(50);
        }

        return view('reports.ar_subledger', compact('tab', 'unpaidInvoices', 'payments', 'returns', 'accountCode'));
    }

    // ==========================================
    // 6. MANAJEMEN HUTANG (DENGAN DRILL-DOWN FILTER)
    // ==========================================
    public function apSubledger(Request $request)
    {
        $tab = $request->get('tab', 'tagihan'); 
        $accountCode = $request->get('account_code'); // Tangkap parameter hyperlink
        
        $kodeAkunHutang = $accountCode ? [$accountCode] : [config('coa.hutang_usaha')];   

        $unpaidBills = collect();
        $payments = collect();
        $returns = collect();

        if ($tab === 'tagihan') {
            // 1. Tagihan Hutang Belum Lunas (Subquery Optimization)
            $subquery = DB::table('journal_details')
                ->select('journal_id',
                    DB::raw('SUM(CASE WHEN position = "KREDIT" THEN amount ELSE -amount END) as remaining_balance'),
                    DB::raw('SUM(CASE WHEN position = "KREDIT" THEN amount ELSE 0 END) as total_invoice')
                )
                ->whereIn('account_code', $kodeAkunHutang)
                ->groupBy('journal_id')
                ->having('remaining_balance', '>', 0);

            $unpaidBills = DB::table('journal_headers')
                ->joinSub($subquery, 'details', 'journal_headers.journal_id', '=', 'details.journal_id')
                ->select('journal_headers.evidence_number', 'journal_headers.transaction_date', 'journal_headers.description', 'details.remaining_balance', 'details.total_invoice')
                ->orderBy('journal_headers.transaction_date', 'asc')
                ->simplePaginate(50);
        } elseif ($tab === 'pembayaran') {
            // 2. Riwayat Pembayaran Hutang (SimplePaginate Optimization)
            $payments = DB::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->whereIn('journal_details.account_code', $kodeAkunHutang)
                ->where('journal_details.position', 'DEBET')
                ->where('journal_headers.description', 'NOT LIKE', '%Retur%')
                ->select('journal_headers.transaction_date', 'journal_headers.evidence_number', 'journal_headers.description', 'journal_details.amount')
                ->orderBy('journal_headers.transaction_date', 'desc')
                ->simplePaginate(50);
        } elseif ($tab === 'retur') {
            // 3. Riwayat Retur Pembelian (SimplePaginate Optimization)
            $returns = DB::table('journal_details')
                ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
                ->whereIn('journal_details.account_code', $kodeAkunHutang)
                ->where('journal_details.position', 'DEBET')
                ->where('journal_headers.description', 'LIKE', '%Retur%')
                ->select('journal_headers.transaction_date', 'journal_headers.evidence_number', 'journal_headers.description', 'journal_details.amount')
                ->orderBy('journal_headers.transaction_date', 'desc')
                ->simplePaginate(50);
        }

        return view('reports.ap_subledger', compact('tab', 'unpaidBills', 'payments', 'returns', 'accountCode'));
    }
}