<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\JournalHeader;
use App\Models\JournalDetail;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AccountImport;
use App\Support\NumberParser;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $coa_type = $request->get('coa_type');
        $report_pos = $request->get('report_pos');

        $query  = Account::query();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('account_code', 'like', '%' . $search . '%')
                  ->orWhere('account_name', 'like', '%' . $search . '%');
            });
        }
        
        if (!empty($coa_type)) {
            $query->where('coa_type', $coa_type);
        }
        
        if (!empty($report_pos)) {
            $query->where('report_pos', $report_pos);
        }

        $accounts = $query->withCount(['journalDetails'])
                          ->orderBy('account_code', 'asc')
                          ->paginate(50)
                          ->appends(request()->query());

        return view('account.index', compact('accounts', 'search'));
    }

    public function export(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AccountExport($request), 
            'coa_export_' . date('YmdHis') . '.xlsx'
        );
    }


    // =========================================================================================
    // FITUR TAMBAH AKUN MANUAL
    // =========================================================================================
    public function create()
    {
        return view('account.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'account_code'   => 'required|string|unique:accounts,account_code|max:50',
            'account_name'   => 'required|string|max:255',
            'coa_type'       => 'required|string',
            'normal_balance' => 'required|in:DEBET,KREDIT',
            'report_pos'     => 'required|string'
        ]);

        Account::create($request->only([
            'account_code',
            'account_name', 
            'coa_type',
            'normal_balance',
            'report_pos'
        ]));

        SystemLog::record('CREATE', 'Master COA', 'Menambahkan akun baru: ' . $request->account_code . ' - ' . $request->account_name);

        return redirect()->route('account.index')->with('success', 'Master akun baru berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $account = Account::where('account_code', $id)->first() ?? Account::findOrFail($id);
        return view('account.edit', compact('account'));
    }

    public function update(Request $request, $id)
    {
        $account = Account::where('account_code', $id)->first() ?? Account::findOrFail($id);

        $request->validate([
            'account_name'   => 'required|string|max:255',
            'coa_type'       => 'required',
            'normal_balance' => 'required|in:DEBET,KREDIT',
            'report_pos'     => 'required'
        ]);

        $account->update($request->only([
            'account_name',
            'coa_type',
            'normal_balance',
            'report_pos'
        ]));

        SystemLog::record('UPDATE', 'Master COA', 'Mengubah data akun: ' . $account->account_code . ' - ' . $account->account_name);

        return redirect()->route('account.index')->with('success', 'Data akun ' . $account->account_code . ' berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $account = Account::where('account_code', $id)->first() ?? Account::findOrFail($id);

        if ($account->journalDetails()->count() > 0) {
            return redirect()->back()->with('error', 'Gagal: Akun ini memiliki riwayat transaksi jurnal dan tidak boleh dihapus.');
        }
        
        SystemLog::record('DELETE', 'Master COA', 'Menghapus akun: ' . $account->account_code . ' - ' . $account->account_name);
        $account->delete();
        return redirect()->back()->with('success', 'Akun ' . $account->account_code . ' telah dihapus secara permanen.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->ids;
        if (!$ids || count($ids) == 0) {
            return redirect()->back()->with('error', 'Pilih minimal satu akun untuk dihapus.');
        }

        $deletedCount = 0;
        $failedCount  = 0;

        $accounts = Account::withCount('journalDetails')->whereIn('account_code', $ids)->get();

        foreach ($accounts as $account) {
            if ($account->journal_details_count == 0) {
                $account->delete();
                $deletedCount++;
            } else {
                $failedCount++;
            }
        }

        if ($failedCount > 0) {
            SystemLog::record('DELETE', 'Master COA', "Menghapus $deletedCount akun terpilih ($failedCount akun gagal karena memiliki transaksi).");
            return redirect()->back()->with('success', "$deletedCount akun berhasil dihapus. $failedCount akun gagal dihapus karena sudah memiliki transaksi.");
        }

        SystemLog::record('DELETE', 'Master COA', "Berhasil menghapus $deletedCount akun terpilih.");
        return redirect()->back()->with('success', "Berhasil menghapus $deletedCount akun terpilih.");
    }

    // =========================================================================================
    // FITUR IMPORT MASSAL COA 
    // =========================================================================================
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file_excel' => 'required|file'
            ]);

            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus berekstensi .xlsx, .xls, atau .csv');
            }

            Excel::import(new AccountImport, $request->file('file_excel'));
            
            SystemLog::record('IMPORT', 'Master COA', 'Import master akun dari file Excel berhasil.');
            
            return redirect()->back()->with('success', 'Daftar Akun (COA) berhasil di-import dan disinkronisasi ke database!');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = collect($e->errors())->flatten()->implode(', ');
            return redirect()->back()->with('error', 'Validasi File Ditolak: ' . $errorMessage);
            
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errRows = [];
            foreach ($failures as $failure) {
                $errRows[] = "Baris " . $failure->row() . " (" . implode(', ', $failure->errors()) . ")";
            }
            return redirect()->back()->with('error', 'Gagal Import, format sel Excel tidak valid pada: ' . implode(' | ', array_slice($errRows, 0, 5)));
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal import COA: Pastikan format dan peletakan kolom sesuai template. Detail teknis: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $rows = [
            ['TEMPLATE IMPORT DAFTAR AKUN (COA)'],
            ['Petunjuk: Jangan mengubah urutan kolom. Data dimulai dari baris ke-6.'],
            ['Kolom B: Kode, Kolom C: Nama, Kolom D: Tipe, Kolom E: Saldo, Kolom F: Laporan'],
            [''], 
            ['NO', 'KODE AKUN', 'NAMA AKUN', 'TIPE COA', 'POS SALDO', 'POS LAPORAN'],
            ['1', '11101', 'Kas Besar', 'Cash & Bank', 'DEBET', 'NERACA'],
            ['2', '11102', 'Bank BCA', 'Cash & Bank', 'DEBET', 'NERACA'],
            ['3', '88004', 'Penyesuaian Persediaan Barang', 'Biaya', 'DEBET', 'LABA RUGI'],
        ];

        $callback = function() use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=template_import_akun.csv",
        ]);
    }

    // =========================================================================================
    // SETUP SALDO AWAL (OPENING BALANCE)
    // =========================================================================================
    public function openingBalanceForm()
    {
        $accounts = Account::orderBy('account_code', 'asc')->get();
        // FIX #004: Use user-specific or session-based opening balance key
        $evidenceKey = 'SA-' . (auth()->id() ?? '00000');
        $header = JournalHeader::where('evidence_number', $evidenceKey)->first();
        $existingDetails = [];

        if ($header) {
            $primaryKeyId = $header->journal_id ?? $header->id;
            
            $existingDetails = JournalDetail::where('journal_id', $primaryKeyId)
                ->pluck('amount', 'account_code')
                ->toArray();
        }

        $defaultDate  = date('Y-m-d', strtotime('last day of previous month'));
        $existingDate = $header ? $header->transaction_date : $defaultDate;

        return view('account.opening_balance', compact('accounts', 'existingDetails', 'existingDate'));
    }

    public function openingBalanceStore(Request $request)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'balances'         => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            // FIX #004: Use user-specific opening balance evidence key
            $evidenceKey = 'SA-' . (auth()->id() ?? '00000');

            $header = JournalHeader::updateOrCreate(
                ['evidence_number' => $evidenceKey],
                [
                    'transaction_date' => $request->transaction_date,
                    'description'      => 'SETUP SALDO AWAL SISTEM (OPENING BALANCE)',
                    'jj_id'            => time(), // FIX 1364
                ]
            );

            $primaryKeyId = $header->journal_id ?? $header->id;

            JournalDetail::where('journal_id', $primaryKeyId)->delete();

            $detailsToInsert = [];
            $totalDebet  = 0;
            $totalKredit = 0;

            $accountsMap = Account::pluck('normal_balance', 'account_code')->toArray();

            foreach ($request->balances as $accCode => $rawAmount) {
                $cleanAmount = preg_replace('/[^0-9.-]/', '', $rawAmount);
                $amount      = floatval($cleanAmount);

                if ($amount != 0) {
                    if (isset($accountsMap[$accCode])) {
                        $position = strtoupper($accountsMap[$accCode]);
                    } else {
                        // PERBAIKAN: Hapus '8' dari daftar DEBET normal
                        // Sesuai RULES.md: 8 = Pendapatan Lain (Saldo Normal: KREDIT)
                        $prefix   = substr(trim($accCode), 0, 1);
                        $position = in_array($prefix, ['1', '5', '6', '9']) ? 'DEBET' : 'KREDIT';
                    }

                    if ($position == 'DEBET')  $totalDebet  += abs($amount);
                    else                       $totalKredit += abs($amount);

                    $detailsToInsert[] = [
                        'journal_id'   => $primaryKeyId,
                        'account_code' => $accCode,
                        'helper_code'  => null,
                        'position'     => $position,
                        'amount'       => abs($amount),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                }
            }

            if (round($totalDebet, 2) != round($totalKredit, 2)) {
                DB::rollBack();
                $selisih = abs($totalDebet - $totalKredit);
                return redirect()->back()->withInput()->with('error', 
                    'GAGAL: Total Saldo Awal tidak seimbang! Total Debet: Rp ' . number_format($totalDebet, 2, ',', '.') . 
                    ' | Total Kredit: Rp ' . number_format($totalKredit, 2, ',', '.') . 
                    ' (Ada selisih Rp ' . number_format($selisih, 2, ',', '.') . ')'
                );
            }

            if (!empty($detailsToInsert)) {
                JournalDetail::insert($detailsToInsert);
            }

            DB::commit();
            SystemLog::record('CREATE', 'Saldo Awal', 'Setup saldo awal COA berhasil disimpan dan seimbang.');
            return redirect()->route('account.index')->with('success', 'Setup Saldo Awal COA berhasil disimpan dan seimbang (Balance)!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage());
        }
    }

    public function importOpeningBalance(Request $request)
    {
        $request->validate(['file' => 'required|file', 'tanggal_saldo' => 'required|date']);

        $file = $request->file('file');
        $handle = fopen($file->getPathname(), "r");
        if (!$handle) return back()->with('error', 'Gagal membaca file CSV.');

        fgets($handle); // skip header
        $detailsToInsert = [];
        $totalDebet = 0;
        $totalKredit = 0;
        $now = now();
        $tanggal = $request->tanggal_saldo;
        $skippedAccounts = [];

        // FIX: Menghapus eksekusi ALTER TABLE di level controller karena memicu implicit commit
        // dan dapat menyebabkan database lock pada lingkungan production.

        DB::beginTransaction();
        try {
            // FIX #004: Use user/session-specific opening balance key
            $evidenceKey = 'SA-' . (auth()->id() ?? 'system');
            $header = JournalHeader::updateOrCreate(
                ['evidence_number' => $evidenceKey],
                ['transaction_date' => $tanggal, 'description' => 'SETUP SALDO AWAL SISTEM (OPENING BALANCE)', 'jj_id' => time()] // FIX 1364
            );
            $primaryKeyId = $header->getKey();
            JournalDetail::where('journal_id', $primaryKeyId)->delete();

            $existingAccounts = Account::pluck('account_code')->toArray();
            $accountMapping = ['1133' => '11307'];

            while (($rawLine = fgets($handle)) !== false) {
                if (trim($rawLine) === '') continue;
                $delimiter = str_contains($rawLine, ';') ? ';' : ',';
                $r = str_getcsv($rawLine, $delimiter, '"', '\\');
                if (count($r) < 3) continue;

                $kodeAkun = trim($r[0]);
                if (array_key_exists($kodeAkun, $accountMapping)) {
                    $kodeAkun = $accountMapping[$kodeAkun];
                }

                // FIX: DRY — gunakan NumberParser helper
                $debet  = NumberParser::parseDecimal($r[2] ?? '0');
                $kredit = NumberParser::parseDecimal($r[3] ?? '0');
                if (empty($kodeAkun) || ($debet == 0 && $kredit == 0)) continue;

                if (!in_array($kodeAkun, $existingAccounts)) {
                    $skippedAccounts[] = $kodeAkun;
                    continue;
                }

                if ($debet != 0) {
                    $detailsToInsert[] = ['journal_id' => $primaryKeyId, 'account_code' => $kodeAkun, 'helper_code' => null, 'position' => 'DEBET',  'amount' => $debet,  'created_at' => $now, 'updated_at' => $now];
                    $totalDebet += $debet;
                }
                if ($kredit != 0) {
                    $detailsToInsert[] = ['journal_id' => $primaryKeyId, 'account_code' => $kodeAkun, 'helper_code' => null, 'position' => 'KREDIT', 'amount' => $kredit, 'created_at' => $now, 'updated_at' => $now];
                    $totalKredit += $kredit;
                }
            }
            fclose($handle);

            if (empty($detailsToInsert) && empty($skippedAccounts)) {
                DB::rollBack();
                return back()->with('error', 'Tidak ada data valid. Pastikan format CSV sudah benar.');
            }

            // FIX #5: Validasi balance SEBELUM commit — sebelumnya commit dulu baru cek
            if (round($totalDebet, 2) !== round($totalKredit, 2)) {
                DB::rollBack();
                $selisih = abs($totalDebet - $totalKredit);
                return back()->with('error',
                    'GAGAL: Saldo tidak seimbang! Debet: Rp ' . number_format($totalDebet, 2, ',', '.') .
                    ' | Kredit: Rp ' . number_format($totalKredit, 2, ',', '.') .
                    ' | Selisih: Rp ' . number_format($selisih, 2, ',', '.')
                );
            }

            foreach (array_chunk($detailsToInsert, 200) as $chunk) {
                JournalDetail::insert($chunk);
            }

            DB::commit();

            SystemLog::record('IMPORT', 'Saldo Awal', 'Import saldo awal dari file CSV berhasil.');

            $status = 'success';
            $pesan = "Setup Saldo Awal BERHASIL DAN SEIMBANG (Total: Rp " . number_format($totalDebet, 2, ',', '.') . "). ";
            if (!empty($skippedAccounts)) {
                $status = 'warning';
                $pesan .= "Akun tidak terdaftar di Master (ditolak): " . implode(', ', $skippedAccounts);
            }
            return back()->with($status, $pesan);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function downloadTemplateOpeningBalance()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_saldo_awal.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['KODE AKUN', 'NAMA AKUN', 'DEBET', 'KREDIT'], ';');
            fputcsv($file, [config('coa.piutang_usaha'), 'Kas Besar', '50000000', '0'], ';');
            fputcsv($file, [config('coa.hutang_usaha'), 'Hutang Dagang', '0', '50000000'], ';');
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}