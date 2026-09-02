<?php
// Simpan di: app/Http/Controllers/ReconciliationController.php

namespace App\Http\Controllers;

use App\Services\ReconciliationService;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    /**
     * Tampilkan form upload + (jika ada) hasil rekonsiliasi sebelumnya via session.
     */
    public function index()
    {
        return view('reconciliation.index');
    }

    /**
     * Proses upload file Jubelio, jalankan rekonsiliasi, tampilkan hasil.
     */
    public function compare(Request $request, ReconciliationService $service)
    {
        $request->validate([
            'jubelio_file' => 'required|file|mimes:csv,txt|max:10240',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'tolerance'    => 'nullable|numeric|min:0',
            'format'       => 'nullable|in:auto,coded,native',
        ], [], [
            'jubelio_file' => 'File Sumber Jubelio',
            'start_date'   => 'Tanggal Awal',
            'end_date'     => 'Tanggal Akhir',
        ]);

        $tolerance = $request->filled('tolerance') ? (float) $request->input('tolerance') : 1000.00;
        $format    = $request->input('format', 'auto');

        try {
            $result = $service->reconcile(
                $request->file('jubelio_file'),
                $request->input('start_date'),
                $request->input('end_date'),
                $tolerance,
                $format
            );
        } catch (\Exception $e) {
            return redirect()->route('reconciliation.index')
                ->with('error', 'Gagal memproses file: ' . $e->getMessage());
        }

        return view('reconciliation.index', [
            'result'     => $result,
            'startDate'  => $request->input('start_date'),
            'endDate'    => $request->input('end_date'),
            'tolerance'  => $tolerance,
            'format'     => $format,
        ]);
    }

    /**
     * Export hasil rekonsiliasi (yang MISMATCH / gap saja) ke Excel/CSV
     * untuk ditindaklanjuti tim finance. Menerima payload yang sama dengan
     * compare() karena hasil rekonsiliasi tidak disimpan ke DB (stateless).
     */
    public function exportMismatch(Request $request, ReconciliationService $service)
    {
        $request->validate([
            'jubelio_file' => 'required|file|mimes:csv,txt|max:10240',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'tolerance'    => 'nullable|numeric|min:0',
        ]);

        $tolerance = $request->filled('tolerance') ? (float) $request->input('tolerance') : 1000.00;

        $result = $service->reconcile(
            $request->file('jubelio_file'),
            $request->input('start_date'),
            $request->input('end_date'),
            $tolerance
        );

        $rows = collect($result['rows'])->reject(fn($r) => $r['status'] === 'MATCH');

        $filename = 'Rekonsiliasi_Selisih_' . $request->input('start_date') . '_sd_' . $request->input('end_date') . '.csv';

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Kode Akun', 'Nama Akun', 'Jubelio', 'ERP', 'Selisih', 'Status']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['account_code'],
                    $r['account_name'],
                    number_format($r['jubelio'], 2, ',', '.'),
                    number_format($r['erp'], 2, ',', '.'),
                    number_format($r['selisih'], 2, ',', '.'),
                    $r['status'],
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
