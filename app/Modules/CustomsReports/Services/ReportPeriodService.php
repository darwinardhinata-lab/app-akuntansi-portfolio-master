<?php

namespace App\Modules\CustomsReports\Services;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\CustomsReports\Imports\DokumenPabeanImport;
use App\Modules\CustomsReports\Models\ReportPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ReportPeriodService — Service layer untuk modul Laporan Bea Cukai.
 *
 * Mengelola siklus hidup ReportPeriod: DRAFT → FINAL → DIUNGGAH.
 * Juga menyediakan auto-populate dari dokumen H2H (khusus PEMASUKAN & PENGELUARAN).
 */
class ReportPeriodService
{
    /**
     * Buat periode laporan baru dalam status DRAFT.
     *
     * @throws \RuntimeException jika periode duplikat
     */
    public function createDraft(
        string $reportType,
        int $bulan,
        int $tahun,
        ?string $catatan = null,
        ?int $createdBy = null,
    ): ReportPeriod {
        return DB::transaction(function () use ($reportType, $bulan, $tahun, $catatan, $createdBy) {
            $existing = ReportPeriod::where('report_type', $reportType)
                ->where('periode_bulan', $bulan)
                ->where('periode_tahun', $tahun)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \RuntimeException(
                    "Periode laporan {$reportType} untuk {$bulan}/{$tahun} sudah ada (status: {$existing->status})."
                );
            }

            return ReportPeriod::create([
                'report_type'     => $reportType,
                'periode_bulan'   => $bulan,
                'periode_tahun'   => $tahun,
                'status'          => ReportPeriod::STATUS_DRAFT,
                'catatan'         => $catatan,
                'created_by'      => $createdBy ?? Auth::id(),
            ]);
        });
    }

    /**
     * Finalisasi periode — migrasi status DRAFT → FINAL.
     * Hanya periode DRAFT yang dapat difinalisasi.
     *
     * @throws \RuntimeException jika bukan DRAFT
     */
    public function finalize(ReportPeriod $period, ?int $actorId = null): ReportPeriod
    {
        return DB::transaction(function () use ($period, $actorId) {
            $locked = ReportPeriod::lockForUpdate()->findOrFail($period->id);

            if (! $locked->isDraft()) {
                throw new \RuntimeException(
                    "Periode berstatus {$locked->status} tidak dapat difinalisasi. Hanya DRAFT yang bisa."
                );
            }

            $locked->update([
                'status'        => ReportPeriod::STATUS_FINAL,
                'finalized_at'  => now(),
                'updated_by'    => $actorId ?? Auth::id(),
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Tandai periode sebagai sudah diunggah ke CEISA.
     * Hanya periode FINAL yang dapat ditandai DIUNGGAH.
     *
     * @throws \RuntimeException jika bukan FINAL
     */
    public function markUploaded(ReportPeriod $period, ?int $actorId = null): ReportPeriod
    {
        return DB::transaction(function () use ($period, $actorId) {
            $locked = ReportPeriod::lockForUpdate()->findOrFail($period->id);

            if (! $locked->isFinal()) {
                throw new \RuntimeException(
                    "Periode berstatus {$locked->status} tidak dapat ditandai DIUNGGAH. Hanya FINAL yang bisa."
                );
            }

            $locked->update([
                'status'        => ReportPeriod::STATUS_DIUNGGAH,
                'uploaded_at'   => now(),
                'updated_by'    => $actorId ?? Auth::id(),
            ]);

            return $locked->fresh();
        });
    }

        /**
     * Auto-populate DokumenPabeanLine dari dokumen Customs H2H yang sudah terbit SPPB/NPE.
     *
     * KHUSUS untuk PEMASUKAN (PIB) & PENGELUARAN (PEB) saja.
     * 5 jenis laporan lain (Mutasi/WIP) TIDAK auto-populate — data belum dipetakan (Fase C).
     *
     * [BELUM PASTI - TODO] Pemetaan field CustomsDocument → kolom cbr_dokumen_pabean_lines
     * belum bisa dipastikan karena CustomsDocument belum pernah dipakai untuk submission
     * sungguhan. Isi manual sebagai fallback sampai spesifikasi resmi tersedia.
     *
     * @return array{created: int, warnings: string[]}
     */
    public function populateFromH2HDocuments(ReportPeriod $period): array
    {
        if (! in_array($period->report_type, [ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN], true)) {
            return [
                'created' => 0,
                'warnings' => ["Auto-populate hanya tersedia untuk Pemasukan & Pengeluaran."],
            ];
        }

        // [BELUM PASTI - TODO] PIB→BC23, PEB→BC30 belum dikonfirmasi
        $jenisDokMapping = [
            'PIB' => 'BC23',
            'PEB' => 'BC30',
        ];

        $warnings = [];

        $documents = CustomsDocument::whereIn('document_type', ['PIB', 'PEB'])
            ->whereIn('status', [
                CustomsDocument::STATUS_SPPB_ISSUED,
                CustomsDocument::STATUS_NPE_ISSUED,
            ])
            ->whereYear('responded_at', $period->periode_tahun)
            ->whereMonth('responded_at', $period->periode_bulan)
            ->with('details')
            ->lockForUpdate()
            ->get();

        if ($documents->isEmpty()) {
            $warnings[] = "Tidak ditemukan dokumen H2H (PIB/PEB) dengan status SPPB_ISSUED/NPE_ISSUED untuk periode {$period->periode_bulan}/{$period->periode_tahun}.";
        }

        $created = 0;

        foreach ($documents as $doc) {
            // [BELUM PASTI - TODO] Field mapping belum pasti — fallback manual
            foreach ($doc->details as $detail) {
                \App\Modules\CustomsReports\Models\DokumenPabeanLine::create([
                    'report_period_id'         => $period->id,
                    'jenis_dok_pabean'         => $jenisDokMapping[$doc->document_type] ?? 'TBD',
                    'no_pendaftaran_dok_pabean' => $doc->nomor_pendaftaran ?? $doc->nomor_aju ?? '',
                    'tgl_dok_pabean'           => $doc->responded_at ? $doc->responded_at->format('Y-m-d') : now()->format('Y-m-d'),
                    'no_bukti'                 => $doc->internal_number,
                    'tgl_bukti'                => $doc->responded_at ? $doc->responded_at->format('Y-m-d') : $doc->created_at->format('Y-m-d'),
                    'pihak_terkait'            => 'TBD',
                    'kode_barang'              => $detail->hs_code ?? '',
                    'nama_barang'              => $detail->deskripsi_barang ?? '',
                    'jumlah_barang'            => (float) ($detail->qty ?? 0),
                    'satuan_barang'            => $detail->satuan ?? '',
                    'mata_uang'                => $doc->currency ?? 'IDR',
                    'nilai'                    => (float) ($detail->nilai ?? $doc->total_value ?? 0),
                    'seri_faktur_pajak'        => null,
                    'nilai_faktur_pajak'       => null,
                ]);
                $created++;
            }

            $warnings[] = "Dokumen {$doc->internal_number}: {$doc->details->count()} baris (mapping [BELUM PASTI - TODO]).";
        }

        return [
            'created' => $created,
            'warnings' => $warnings,
        ];
    }

    /**
     * Import Excel untuk dokumen pabean.
     *
     * @return array{created: int, errors: string[]}
     */
    public function importDokumenPabean(ReportPeriod $period, string $filePath): array
    {
        $import = new DokumenPabeanImport($period);
        \Maatwebsite\Excel\Facades\Excel::import($import, $filePath);

        return [
            'created' => $import->getSuccessCount(),
            'errors' => $import->getErrors(),
        ];
    }
}