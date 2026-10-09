<?php

namespace App\Modules\CustomsReports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomsReports\Exports\DokumenPabeanExport;
use App\Modules\CustomsReports\Exports\DokumenPabeanTemplateExport;
use App\Modules\CustomsReports\Exports\MutasiExport;
use App\Modules\CustomsReports\Exports\MutasiTemplateExport;
use App\Modules\CustomsReports\Exports\PosisiExport;
use App\Modules\CustomsReports\Exports\PosisiTemplateExport;
use App\Modules\CustomsReports\Imports\DokumenPabeanImport;
use App\Modules\CustomsReports\Imports\MutasiImport;
use App\Modules\CustomsReports\Imports\PosisiImport;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportPeriodController extends Controller
{
    public function __construct(
        protected ReportPeriodService $service,
    ) {}

    public function index(Request $request)
    {
        $query = ReportPeriod::query()->orderByDesc('periode_tahun')->orderByDesc('periode_bulan');

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        $periods = $query->paginate(20)->appends($request->query());

        return view('customs-reports.index', [
            'periods' => $periods,
            'filters' => $request->only(['report_type']),
            'typeLabels' => ReportPeriod::TYPE_LABELS,
        ]);
    }

    public function create()
    {
        return view('customs-reports.create', [
            // View create juga dipakai untuk edit dan selalu membaca $period->exists.
            // Model baru menjaga kontrak view tanpa perlu conditional variable.
            'period' => new ReportPeriod(),
            'typeLabels' => ReportPeriod::TYPE_LABELS,
            'allTypes' => ReportPeriod::ALL_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:' . implode(',', ReportPeriod::ALL_TYPES),
            'periode_bulan' => 'required|integer|min:1|max:12',
            'periode_tahun' => 'required|integer|min:1900|max:2100',
            'catatan' => 'nullable|string',
        ]);

        try {
            $this->service->createDraft(
                $validated['report_type'],
                (int) $validated['periode_bulan'],
                (int) $validated['periode_tahun'],
                $validated['catatan'] ?? null,
                auth()->id(),
            );

            return redirect()->route('customs-reports.index')
                ->with('success', 'Draft periode laporan berhasil dibuat.');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(ReportPeriod $period)
    {
        $lines = $period->lines()->orderBy('id')->get();
        $rejectAssistData = $period->report_type === ReportPeriod::TYPE_MUTASI_REJECT
            ? $this->service->rejectAssistData($period->periode_bulan, $period->periode_tahun)
            : [];

        return view('customs-reports.show', compact('period', 'lines', 'rejectAssistData'));
    }

    public function edit(ReportPeriod $period)
    {
        if (! $period->isDraft()) {
            return redirect()->route('customs-reports.show', $period)
                ->with('error', 'Hanya periode berstatus DRAFT yang dapat diedit.');
        }

        return view('customs-reports.create', [
            'period' => $period,
            'typeLabels' => ReportPeriod::TYPE_LABELS,
            'allTypes' => ReportPeriod::ALL_TYPES,
        ]);
    }

    public function update(Request $request, ReportPeriod $period)
    {
        if (! $period->isDraft()) {
            return redirect()->route('customs-reports.show', $period)
                ->with('error', 'Hanya periode berstatus DRAFT yang dapat diubah.');
        }

        $validated = $request->validate([
            'catatan' => 'nullable|string',
        ]);

        $period->update(array_merge($validated, ['updated_by' => auth()->id()]));

        return redirect()->route('customs-reports.show', $period)
            ->with('success', 'Periode berhasil diperbarui.');
    }

    public function destroy(ReportPeriod $period)
    {
        if (! $period->isDraft()) {
            return back()->with('error', 'Hanya periode DRAFT yang dapat dihapus.');
        }

                $period->delete();

        return redirect()->route('customs-reports.index')
            ->with('success', 'Periode laporan berhasil dihapus.');
    }

    /**
     * Import Excel — memilih importer yang tepat berdasarkan report_type.
     */
    public function import(Request $request, ReportPeriod $period)
    {
        if (! $period->isDraft()) {
            return back()->with('error', 'Hanya periode DRAFT yang dapat di-import.');
        }

        $request->validate([
            'file_excel' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $filePath = $request->file('file_excel')->getRealPath();

            $import = match ($period->report_type) {
                ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN => new DokumenPabeanImport($period),
                ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, ReportPeriod::TYPE_MUTASI_BARANG_JADI,
                ReportPeriod::TYPE_MUTASI_BARANG_MODAL, ReportPeriod::TYPE_MUTASI_REJECT => new MutasiImport($period),
                ReportPeriod::TYPE_WIP => new PosisiImport($period),
                default => throw new \RuntimeException('Tipe laporan tidak didukung untuk import.'),
            };

            \Illuminate\Support\Facades\DB::transaction(function () use ($period, $import, $filePath) {
                // Hold the same header lock as sync/finalize until imported rows are saved.
                \App\Modules\CustomsReports\Support\ManualReportImport::protect($period);
                Excel::import($import, $filePath);
            });

            $message = "{$import->getSuccessCount()} baris berhasil di-import.";

            if (! empty($import->getErrors())) {
                $message .= ' Namun ada ' . count($import->getErrors()) . ' baris gagal: '
                    . implode(', ', array_slice($import->getErrors(), 0, 5));
                return redirect()->route('customs-reports.show', $period)
                    ->with('error', $message);
            }

            return redirect()->route('customs-reports.show', $period)
                ->with('success', $message);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    /**
     * Export Excel.
     */
    public function export(Request $request, ReportPeriod $period)
    {
        $export = match ($period->report_type) {
            ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN => new DokumenPabeanExport($period),
            ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, ReportPeriod::TYPE_MUTASI_BARANG_JADI,
            ReportPeriod::TYPE_MUTASI_BARANG_MODAL, ReportPeriod::TYPE_MUTASI_REJECT => new MutasiExport($period),
            ReportPeriod::TYPE_WIP => new PosisiExport($period),
            default => throw new \RuntimeException('Tipe laporan tidak didukung untuk export.'),
        };

        $filename = 'Laporan_' . preg_replace('/[^a-zA-Z0-9]/', '_', $period->label())
            . '_' . $period->periode_bulan . '_' . $period->periode_tahun . '.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Download template (header-only).
     */
    public function downloadTemplate(ReportPeriod $period)
    {
        $export = match ($period->report_type) {
            ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN => new DokumenPabeanTemplateExport($period),
            ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, ReportPeriod::TYPE_MUTASI_BARANG_JADI,
            ReportPeriod::TYPE_MUTASI_BARANG_MODAL, ReportPeriod::TYPE_MUTASI_REJECT => new MutasiTemplateExport(),
            ReportPeriod::TYPE_WIP => new PosisiTemplateExport(),
            default => throw new \RuntimeException('Tipe laporan tidak didukung.'),
        };

        $filename = 'Template_' . preg_replace('/[^a-zA-Z0-9]/', '_', $period->label()) . '.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Finalisasi periode.
     */
    public function finalize(Request $request, ReportPeriod $period)
    {
        try {
            $this->service->finalize($period, auth()->id());

            return back()->with('success', 'Periode laporan berhasil difinalisasi.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Tandai periode sebagai sudah diunggah ke CEISA.
     */
    public function markUploaded(Request $request, ReportPeriod $period)
    {
        try {
            $this->service->markUploaded($period, auth()->id());

            return back()->with('success', 'Periode laporan berhasil ditandai sebagai DIUNGGAH.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Auto-populate dari dokumen H2H (hanya PEMASUKAN & PENGELUARAN).
     */
    public function populateFromH2H(Request $request, ReportPeriod $period)
    {
        if (! config('customs.enabled', false)) {
            return back()->with('error', __('customs_settings.locked'));
        }
        if (! in_array($period->report_type, [ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN], true)) {
            return back()->with('error', 'Auto-populate hanya tersedia untuk laporan Pemasukan & Pengeluaran.');
        }

        if (! $period->isDraft()) {
            return back()->with('error', 'Hanya periode DRAFT yang dapat di-populate.');
        }

        try {
            $result = $this->service->populateFromH2HDocuments($period);

            $message = "{$result['created']} baris berhasil di-import dari dokumen H2H.";

            if (! empty($result['warnings'])) {
                $message .= ' ' . implode(' ', array_slice($result['warnings'], 0, 3));
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal populate dari H2H: ' . $e->getMessage());
        }
    }

    /**
     * Rebuild any of the seven draft reports from read-only operational sources.
     */
    public function populateMutasi(Request $request, ReportPeriod $period)
    {
        if (! $period->isDraft()) {
            return back()->with('error', 'Hanya periode DRAFT yang dapat di-populate.');
        }

        try {
            if ($period->source_mode !== 'INTERNAL') {
                throw new \RuntimeException('Periode manual/legacy dilindungi dari rebuild internal. Buat periode internal terpisah setelah rekonsiliasi.');
            }
            app(\App\Modules\CustomsReports\Services\InternalReportSyncService::class)->sync($period);

            return back()->with('success', 'Data laporan berhasil disinkronkan otomatis dari sistem.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal sinkronisasi laporan: ' . $e->getMessage());
        }
    }

}