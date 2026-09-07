<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\Account;
use App\Models\HelperCode;
use App\Models\Asset;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\JournalImport;
use App\Jobs\SyncDashboardToTempJob;
use App\Services\JournalCsvImportService;
use App\Support\JournalBalanceValidator;

class JournalController extends Controller
{
    protected $journalCsvImportService;

    public function __construct(JournalCsvImportService $journalCsvImportService)
    {
        $this->journalCsvImportService = $journalCsvImportService;
    }

    public function index(Request $request)
    {
        $perPage   = $request->get('per_page', 50);
        $search    = $request->get('search');
        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date');

        $query = JournalHeader::with(['details.account']);

        if (!empty($startDate)) {
            $query->whereDate('transaction_date', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('transaction_date', '<=', $endDate);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('evidence_number', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('details', function($qDet) use ($search) {
                      $qDet->where('account_code', 'like', '%' . $search . '%')
                           ->orWhereHas('account', function($qAcc) use ($search) {
                               $qAcc->where('account_name', 'like', '%' . $search . '%');
                           });
                  });
            });
        }

        $journals = $query->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends([
                'per_page'   => $perPage,
                'search'     => $search,
                'start_date' => $startDate,
                'end_date'   => $endDate,
            ]);

        return view('journal.index', compact('journals', 'perPage', 'search', 'startDate', 'endDate'));
    }

    public function create()
    {
        $accounts = Account::orderBy('account_code', 'asc')->get();
        $helpers  = HelperCode::orderBy('helper_code', 'asc')->get();

        return view('journal.create', compact('accounts', 'helpers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'evidence_number'  => 'nullable|string|max:100',
            'description'      => 'required|string|max:255',
            'details'          => 'required|array|min:2',
            'details.*.account_code' => 'required|string',
            'details.*.position'     => 'required|in:DEBET,KREDIT',
            'details.*.amount'       => 'required|numeric|min:0.01',
            'details.*.helper_code'  => 'nullable|string',
        ]);

        // FIX 5: Tambahkan validasi perlindungan Balance Jurnal dari serangan Bypass
        if (!JournalBalanceValidator::isBalanced($request->details)) {
            $selisih = JournalBalanceValidator::getDifference($request->details);
            return redirect()->back()->withInput()->with('error', 'Gagal: Total Debet dan Kredit pada jurnal tidak seimbang (Unbalanced). Selisih: Rp ' . number_format($selisih, 2, ',', '.'));
        }

        $validatedData = $request->only([
            'transaction_date',
            'evidence_number',
            'description'
        ]);

        try {
            DB::beginTransaction();

            $journal = JournalHeader::create([
                'transaction_date' => $request->transaction_date,
                'evidence_number'  => $request->evidence_number,
                'description'      => $request->description,
            ]);

            // B9 FIX: Gunakan empty() eksplisit — operator ?? tidak menangkap string kosong ''
            // jika kolom journal_id ada di DB tapi isinya null/kosong, ?? tidak terpicu.
            $primaryKeyId = !empty($journal->journal_id) ? $journal->journal_id : $journal->id;
            if (empty($primaryKeyId)) {
                throw new \Exception('Gagal membuat jurnal: primary key tidak terbentuk. Cek model JournalHeader.');
            }

            // Pre-load semua akun yang dibutuhkan dalam satu query (whereIn)
            $accountCodes = collect($request->details)
                ->pluck('account_code')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            $accountsMap = Account::whereIn('account_code', $accountCodes)
                ->get(['account_code', 'coa_type', 'account_name'])
                ->keyBy('account_code');

            foreach ($request->details as $detail) {
                if (isset($detail['amount']) && $detail['amount'] != 0) {

                    $savedDetail = JournalDetail::create([
                        'journal_id'   => $primaryKeyId,
                        'account_code' => $detail['account_code'],
                        'helper_code'  => $detail['helper_code'] ?? null,
                        'position'     => $detail['position'],
                        'amount'       => $detail['amount'],
                    ]);

                    // DETEKSI AKUN ASET TETAP: Hanya akun kode 12000
                    $isFixedAsset = $detail['account_code'] === config('coa.aset_tetap');
                    if ($detail['position'] === 'DEBET' && $isFixedAsset) {
                        Asset::updateOrCreate(
                            ['journal_detail_id' => $savedDetail->getKey()],
                            [
                                'asset_code'         => 'AST-' . $savedDetail->getKey() . '-' . date('Ymd', strtotime($request->transaction_date)),
                                'asset_name'         => $request->description,
                                'purchase_date'      => $request->transaction_date,
                                'purchase_price'     => $detail['amount'],
                                'useful_life_months' => 0,
                            ]
                        );
                    }
                }
            }

            DB::commit();
            SystemLog::record('CREATE', 'Jurnal Umum', 'Menambahkan transaksi jurnal: ' . ($request->evidence_number ?? 'OTOMATIS'));
            return redirect()->route('jurnal.index')->with('success', 'Transaksi Jurnal berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $journal  = JournalHeader::with('details')->findOrFail($id);
        $accounts = Account::orderBy('account_code', 'asc')->get();
        $helpers  = HelperCode::orderBy('helper_code', 'asc')->get();

        return view('journal.edit', compact('journal', 'accounts', 'helpers'));
    }

    public function update(Request $request, $id)
    {
        if (!JournalBalanceValidator::isBalanced($request->details)) {
            $selisih = JournalBalanceValidator::getDifference($request->details);
            return redirect()->back()->with('error', 'Gagal: Total Debet dan Kredit pada perubahan jurnal tidak seimbang. Selisih: Rp ' . number_format($selisih, 2, ',', '.'));
        }

        try {
            DB::beginTransaction();
            $journal = JournalHeader::findOrFail($id);

            $journal->update([
                'transaction_date' => $request->transaction_date,
                'evidence_number'  => $request->evidence_number,
                'description'      => $request->description,
            ]);

            // Hapus aset tetap lama yang terkait detail jurnal ini sebelum detail di-recreate
            $oldDetailIds = $journal->details()->pluck('id')->toArray();
            if (!empty($oldDetailIds)) {
                \App\Models\Asset::whereIn('journal_detail_id', $oldDetailIds)->delete();
            }

            $journal->details()->delete();

            // Re-create detail dan auto-detect aset tetap (akun 12000 DEBET)
            foreach ($request->details as $detail) {
                if (isset($detail['amount']) && $detail['amount'] != 0) {
                    $savedDetail = JournalDetail::create([
                        'journal_id'   => $id,
                        'account_code' => $detail['account_code'],
                        'helper_code'  => $detail['helper_code'] ?? null,
                        'position'     => $detail['position'],
                        'amount'       => $detail['amount'],
                    ]);

                    // DETEKSI AKUN ASET TETAP: Re-create aset jika akun 12000 DEBET
                    if ($detail['position'] === 'DEBET' && $detail['account_code'] === config('coa.aset_tetap')) {
                        Asset::updateOrCreate(
                            ['journal_detail_id' => $savedDetail->getKey()],
                            [
                                'asset_code'         => 'AST-' . $savedDetail->getKey() . '-' . date('Ymd', strtotime($request->transaction_date)),
                                'asset_name'         => $request->description,
                                'purchase_date'      => $request->transaction_date,
                                'purchase_price'     => $detail['amount'],
                                'useful_life_months' => 0,
                            ]
                        );
                    }
                }
            }

            DB::commit();
            SystemLog::record('UPDATE', 'Jurnal Umum', 'Mengubah transaksi jurnal: ' . ($journal->evidence_number ?? $id));
            return redirect()->route('jurnal.index')->with('success', 'Perubahan jurnal berhasil disimpan!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $journal = JournalHeader::findOrFail($id);

            // Hapus aset tetap yang terhubung ke detail jurnal ini sebelum detail dihapus
            $detailIds = $journal->details()->pluck('id')->toArray();
            if (!empty($detailIds)) {
                \App\Models\Asset::whereIn('journal_detail_id', $detailIds)->delete();
            }

            $journal->details()->delete();
            $journal->delete();
            DB::commit();
            SystemLog::record('DELETE', 'Jurnal Umum', 'Menghapus transaksi jurnal: ' . ($journal->evidence_number ?? $id));
            return redirect()->back()->with('success', 'Transaksi Jurnal berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function massDestroy(Request $request)
    {
        $ids = $request->input('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()->back()->with('error', 'Pilih minimal satu transaksi jurnal yang ingin dihapus.');
        }

        try {
            DB::beginTransaction();

            $journals = JournalHeader::whereIn('journal_id', $ids)->get();
            if ($journals->isEmpty()) {
                $journals = JournalHeader::whereIn('id', $ids)->get();
            }

            $journalIds = $journals->pluck('journal_id')->toArray();

            if (!empty($journalIds)) {
                // Hapus aset tetap yang terhubung ke detail jurnal yang akan dihapus
                $detailIds = JournalDetail::whereIn('journal_id', $journalIds)->pluck('id')->toArray();
                if (!empty($detailIds)) {
                    \App\Models\Asset::whereIn('journal_detail_id', $detailIds)->delete();
                }

                JournalDetail::whereIn('journal_id', $journalIds)->delete();
                JournalHeader::whereIn('journal_id', $journalIds)->delete();
            }

            $deletedCount = count($journalIds);

            DB::commit();
            SystemLog::record('DELETE', 'Jurnal Umum', 'Menghapus ' . $deletedCount . ' transaksi jurnal secara massal.');
            return redirect()->back()->with('success', $deletedCount . ' transaksi jurnal berhasil dihapus secara massal!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal melakukan hapus massal: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', 3600);
            @ini_set('memory_limit', '1024M');
        }
        DB::disableQueryLog();

        // 1. TANGKAP SILENT ERROR JIKA UKURAN FILE TERLALU BESAR UNTUK SERVER
        if (!$request->hasFile('file_excel') || !$request->file('file_excel')->isValid()) {
            return redirect()->back()->with('error', 'GAGAL UPLOAD: File kosong atau ukuran melebihi batas maksimal server. Jika data terlalu besar, pisahkan/pecah file CSV menjadi 2 bagian lalu upload berurutan.');
        }

        // Panggil Service untuk pemrosesan bisnis yang kompleks
        $result = $this->journalCsvImportService->import($request->file('file_excel'));

        if ($result['status'] === 'success') {
            return redirect()->back()->with('success', $result['message']);
        } else {
            return redirect()->back()->with('error', $result['message']);
        }
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Jurnal_Jubelio.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, array('Tanggal', 'No Jurnal', 'No Bukti', 'Deskripsi', 'Total Debet', 'Total Kredit', 'Nilai Debet', 'Nilai Kredit', 'Akun'), ',');
            fputcsv($file, array('20 May 2026', 'GJ-1424034', 'INV-001151700', 'TOTAL', '142.000,00', '142.000,00', '42.000,00', '0,00', '5-5000 - Harga Pokok Penjualan'), ',');
            fputcsv($file, array('20 May 2026', 'GJ-1424034', 'INV-001151700', 'TOTAL', '142.000,00', '142.000,00', '0,00', '42.000,00', '1-1200 - Persediaan Barang'), ',');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dispatchSyncJob()
    {
        SyncDashboardToTempJob::dispatch();

        return response()->json([
            'status' => 'success',
            'message' => 'Proses sinkronisasi berhasil dijalankan di background.'
        ]);
    }

    public function getJournalDetailsAjax(Request $request)
    {
        $evidence = $request->get('evidence_number');
        if (!$evidence) {
            return response()->json(['status' => 'error', 'message' => 'Nomor Bukti tidak valid.']);
        }

        $journals = DB::table('journal_details')
            ->join('journal_headers', 'journal_details.journal_id', '=', 'journal_headers.journal_id')
            ->leftJoin('accounts', 'journal_details.account_code', '=', 'accounts.account_code')
            ->where('journal_headers.evidence_number', $evidence)
            ->select(
                'journal_headers.transaction_date',
                'journal_headers.notes as header_desc',
                'accounts.account_code',
                'accounts.account_name',
                'journal_details.position',
                'journal_details.amount'
            )
            ->orderBy('journal_details.id', 'asc')
            ->get();

        if ($journals->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Detail Jurnal tidak ditemukan.']);
        }

        // FIX #011: Escape evidence number to prevent XSS
        $escapedEvidence = e($evidence);
        $html = view('journal.partials.ajax_detail', compact('journals', 'escapedEvidence'))->render();

        return response()->json([
            'status' => 'success',
            'html' => $html
        ]);
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $search = $request->get('search');

        $filename = 'Jurnal_Umum_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new \App\Exports\JournalExport($startDate, $endDate, $search), $filename);
    }
}
