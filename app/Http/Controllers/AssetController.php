<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AssetImport;
use App\Exports\AssetExport;

class AssetController extends Controller
{
    /**
     * Display the main asset list with depreciation calculations.
     * Lists assets that have been auto-created from journal entries with account code 12000.
     * Users can set useful_life_months manually for depreciation calculation.
     */
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date');
        $perPage   = $request->get('per_page', 50);

        $query = Asset::with('journalDetail.header.details.account')
            ->orderBy('purchase_date', 'desc');

        if (!empty($startDate)) {
            $query->whereDate('purchase_date', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('purchase_date', '<=', $endDate);
        }
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', '%' . $search . '%')
                  ->orWhere('asset_name', 'like', '%' . $search . '%');
            });
        }

        $assets = $query->paginate($perPage)->appends([
            'per_page'   => $perPage,
            'search'     => $search,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ]);

        $today = Carbon::now();

        // Calculate depreciation values dynamically
        foreach ($assets as $asset) {
            $asset->depreciation_per_month = 0;
            $asset->accumulated            = 0;
            $asset->book_value             = $asset->purchase_price;

            if ($asset->useful_life_months > 0) {
                $start = Carbon::parse($asset->purchase_date);
                $diff  = $start->diffInMonths($today);
                $age   = min(max(0, $diff), $asset->useful_life_months);

                $depreciableAmount = $asset->purchase_price - $asset->residual_value;
                $asset->depreciation_per_month = $depreciableAmount / $asset->useful_life_months;
                $asset->accumulated            = $age * $asset->depreciation_per_month;
                $asset->book_value             = $asset->purchase_price - $asset->accumulated;
            }
        }

        return view('asset.index', compact('assets', 'search', 'startDate', 'endDate', 'perPage'));
    }

    /**
     * Toggle active/inactive status of an asset.
     */
    public function toggleStatus(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $asset->update(['is_active' => !$asset->is_active]);

        SystemLog::record('UPDATE', 'Aset Management', 'Toggle status aset: ' . $asset->asset_code . ' -> ' . ($asset->is_active ? 'Aktif' : 'Tidak Aktif'));

        return response()->json([
            'success' => true,
            'is_active' => $asset->is_active,
            'message' => 'Status aset berhasil diubah menjadi ' . ($asset->is_active ? 'Aktif' : 'Tidak Aktif') . '!'
        ]);
    }

    /**
     * Show the form for setting useful life months on an existing asset.
     * Assets are auto-created from journal entries, users only set useful life (lama penyusutan).
     */
    public function create($journal_detail_id = null)
    {
        $asset = null;
        $journalDetail = null;

        if ($journal_detail_id) {
            $asset = Asset::where('journal_detail_id', $journal_detail_id)->first();
        }

        // Tampilkan semua aset yang belum ada umur penyusutan (termasuk aset import manual)
        $assetsNeedingInput = Asset::where('useful_life_months', 0)
            ->with('journalDetail.header')
            ->orderBy('purchase_date', 'desc')
            ->limit(200)
            ->get();

        $today = Carbon::now();

        // Calculate depreciation values dynamically for display
        foreach ($assetsNeedingInput as $asset) {
            $asset->depreciation_per_month = 0;
            $asset->accumulated            = 0;
            $asset->book_value             = $asset->purchase_price;

            // Note: Since useful_life_months = 0, these values will be 0
            // They will be calculated after user sets the useful_life_months
        }

        return view('asset.create', compact('asset', 'journalDetail', 'assetsNeedingInput', 'today'));
    }

    /**
     * Store useful_life_months for an existing asset.
     * Mendukung aset dari jurnal maupun aset import manual (tanpa journal_detail_id).
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id'           => 'required|exists:assets,id',
            'useful_life_months' => 'required|integer|min:1',
        ]);

        try {
            $asset = Asset::findOrFail($request->asset_id);

            $asset->update([
                'useful_life_months' => $request->useful_life_months,
            ]);

            SystemLog::record('UPDATE', 'Aset Management', 'Mengatur umur penyusutan aset: ' . $asset->asset_code);

            return redirect()->route('aset.index')->with('success', 'Umur penyusutan aset "' . $asset->asset_name . '" berhasil disimpan!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update the useful life of an asset.
     */
    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        $request->validate([
            'useful_life_months' => 'required|numeric|min:0'
        ]);

        $asset->update(['useful_life_months' => $request->useful_life_months]);

        SystemLog::record('UPDATE', 'Aset Management', 'Mengubah umur penyusutan aset: ' . $asset->asset_name);

        return redirect()->back()->with('success', 'Umur penyusutan aset berhasil diperbarui!');
    }

    /**
     * Delete an asset.
     */
    public function destroy($id)
    {
        try {
            $asset = Asset::findOrFail($id);
            $assetCode = $asset->asset_code;
            $assetName = $asset->asset_name;
            $asset->delete();

            SystemLog::record('DELETE', 'Aset Management', 'Menghapus aset: ' . $assetCode . ' - ' . $assetName);

            return redirect()->back()->with('success', 'Aset "' . $assetCode . '" berhasil dihapus!');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus aset: ' . $e->getMessage());
        }
    }

    /**
     * Display the depreciation list (matrix view).
     */
    public function depreciationList()
    {
        $assets = Asset::with('journalDetail.header.details.account')
            ->orderBy('purchase_date', 'asc')
            ->get();
        $today = Carbon::now();

        foreach ($assets as $asset) {
            if ($asset->useful_life_months > 0) {
                $start = Carbon::parse($asset->purchase_date);
                $diff  = $start->diffInMonths($today);
                $age   = min(max(0, $diff), $asset->useful_life_months);

                $depreciableAmount = $asset->purchase_price - $asset->residual_value;
                $asset->depreciation_per_month = $depreciableAmount / $asset->useful_life_months;
                $asset->accumulated            = $age * $asset->depreciation_per_month;
                $asset->book_value             = $asset->purchase_price - $asset->accumulated;
            } else {
                $asset->depreciation_per_month = 0;
                $asset->accumulated            = 0;
                $asset->book_value             = $asset->purchase_price;
            }
        }

        return view('asset.list', compact('assets', 'today'));
    }

    /**
     * Generate depreciation journal entries automatically.
     */
    public function generateDepreciation()
    {
        try {
            DB::beginTransaction();
            $now = Carbon::now();

            $period = $now->format('Ym');
            $monthName = $now->translatedFormat('F Y');
            $evidenceNumber = 'DEP-' . $period;

            $exists = JournalHeader::where('evidence_number', $evidenceNumber)->exists();
            if ($exists) {
                return redirect()->back()->with('error', "GAGAL: Jurnal penyusutan untuk bulan {$monthName} sudah pernah diproses sebelumnya! Anda tidak boleh menjurnal dua kali di bulan yang sama.");
            }

            $assets = Asset::where('useful_life_months', '>', 0)->get();
            $totalDepreciation = 0;
            $detailsToInsert = [];

            $journalId = JournalHeader::generateNextId();
            JournalHeader::create([
                'journal_id'       => $journalId,
                'transaction_date' => $now->endOfMonth()->format('Y-m-d'),
                'evidence_number'  => $evidenceNumber,
                'description'      => "Penyusutan Aset Tetap - Bulan {$monthName}",
                'transaction_type' => 'Penyusutan Aset',
            ]);

            foreach ($assets as $asset) {
                $start = Carbon::parse($asset->purchase_date);
                if ($start->format('Ym') > $period) continue;

                $age = $start->diffInMonths($now);
                if ($age >= $asset->useful_life_months) continue;

                $depreciableAmount = $asset->purchase_price - $asset->residual_value;
                $depPerMonth = $depreciableAmount / $asset->useful_life_months;
                $totalDepreciation += $depPerMonth;

                $detailsToInsert[] = [
                    'journal_id'   => $journalId,
                    'account_code' => config('coa.akum_penyusutan'),
                    'helper_code'  => null,
                    'position'     => 'KREDIT',
                    'amount'       => $depPerMonth,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            if ($totalDepreciation == 0) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Tidak ada aset valid yang perlu disusutkan pada bulan ini.');
            }

            $detailsToInsert[] = [
                'journal_id'   => $journalId,
                'account_code' => config('coa.beban_penyusutan'),
                'helper_code'  => null,
                'position'     => 'DEBET',
                'amount'       => $totalDepreciation,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            JournalDetail::insert($detailsToInsert);

            DB::commit();
            SystemLog::record('CREATE', 'Aset Management', 'Jurnal penyusutan aset bulan ' . $monthName . ' sebesar Rp ' . number_format($totalDepreciation, 2, ',', '.') . ' berhasil dibuat.');
            return redirect()->back()->with('success', "SEMPURNA! Jurnal Penyusutan Aset sebesar Rp " . number_format($totalDepreciation, 2, ',', '.') . " untuk bulan {$monthName} berhasil diposting ke Buku Besar.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ====================================================================================
    // IMPORT, EXPORT, AND TEMPLATE FEATURES
    // ====================================================================================

    /**
     * Import assets from CSV/Excel file.
     */
    public function import(Request $request)
    {
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', 3600);
            @ini_set('memory_limit', '1024M');
        }
        DB::disableQueryLog();

        if (!$request->hasFile('file_excel') || !$request->file('file_excel')->isValid()) {
            return redirect()->back()->with('error', 'GAGAL UPLOAD: File kosong atau ukuran melebihi batas maksimal server.');
        }

        $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
        }

        try {
            $import = new AssetImport($request->file('file_excel')->getRealPath());
            Excel::import($import, $request->file('file_excel'));

            $imported = $import->getImportedCount();
            $skipped = $import->getSkippedCount();
            $errors = $import->getErrors();

            $message = "Import Berhasil! {$imported} aset terekam.";
            if ($skipped > 0) {
                $message .= " ({$skipped} baris dilewati)";
            }
            if (!empty($errors)) {
                $message .= " Error: " . implode(' | ', array_slice($errors, 0, 3));
            }

            return redirect()->back()->with('success', $message);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errRows = [];
            foreach ($failures as $failure) {
                $errRows[] = "Baris " . $failure->row() . " (" . implode(', ', $failure->errors()) . ")";
            }
            return redirect()->back()->with('error', 'Gagal Import, format sel Excel tidak valid pada: ' . implode(' | ', array_slice($errRows, 0, 5)));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal import aset: Pastikan format dan peletakan kolom sesuai template. Detail: ' . $e->getMessage());
        }
    }

    /**
     * Download the import template for assets.
     */
    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Import_Aset_Management.csv"',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'No', 'Kode Aset', 'Nama Aset', 'Kategori', 'Qty',
                'Tgl. Pemakaian', 'Nilai Perolehan', 'Akumulasi Penyusutan',
                'Nilai Buku', 'Nilai Sisa', 'Status'
            ], ';');

            fputcsv($file, [
                '1', 'AST-001', 'Laptop Kantor', 'Elektronik', '1',
                '2024-01-15', '15000000', '0', '15000000', '0', 'Aktif'
            ], ';');

            fputcsv($file, [
                '2', 'AST-002', 'Mobil Dinas', 'Kendaraan', '1',
                '2024-03-01', '250000000', '0', '250000000', '0', 'Aktif'
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export assets to Excel.
     */
    public function export(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $search = $request->get('search');

        $filename = 'Aset_Management_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new AssetExport($startDate, $endDate, $search), $filename);
    }
}