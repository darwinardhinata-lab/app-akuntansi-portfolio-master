<?php
// Simpan di: app/Http/Controllers/ReconciliationController.php

namespace App\Http\Controllers;

use App\Services\ReconciliationService;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function index()
    {
        return view('reconciliation.index');
    }

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
}
