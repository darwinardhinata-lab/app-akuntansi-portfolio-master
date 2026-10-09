<?php

namespace App\Modules\CustomsReports\Services;

use App\Models\Product;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\CustomsReports\Imports\DokumenPabeanImport;
use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use Carbon\Carbon;
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

            $period = ReportPeriod::create([
                'report_type'     => $reportType,
                'periode_bulan'   => $bulan,
                'periode_tahun'   => $tahun,
                'status'          => ReportPeriod::STATUS_DRAFT,
                'source_mode' => !config('customs.enabled') && app(CustomsSettingsService::class)->autoSyncInternal() ? 'INTERNAL' : 'MANUAL',
                'catatan'         => $catatan,
                'created_by'      => $createdBy ?? Auth::id(),
            ]);

            if (! config('customs.enabled') && app(CustomsSettingsService::class)->autoSyncInternal()) {
                app(InternalReportSyncService::class)->sync($period);
            }

            return $period;
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

            if (app(CustomsSettingsService::class)->autoSyncInternal() && ! config('customs.enabled')) {
                app(InternalReportSyncService::class)->sync($locked);
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
                    'no_aju'                  => $doc->nomor_aju,
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

    /**
     * Auto-populate Mutasi Bahan Baku & Penolong from the read-only
     * Manufacturing material ledger. Only cbr_mutasi_lines is written.
     */
    public function populateMutasiBahanBaku(ReportPeriod $period): void
    {
        $this->ensureDraftType($period, ReportPeriod::TYPE_MUTASI_BAHAN_BAKU);
        $this->populateMutasiFromLedger($period, 'mfg_material_ledgers', 'item_id', function ($item): array {
            return $this->resolveMaterialIdentity($item->item_type, (int) $item->item_id);
        }, ['item_type']);
    }

    /**
     * Auto-populate Mutasi Barang Jadi from the read-only inventory ledger.
     * Only products referenced by a Manufacturing work order are included.
     */
    public function populateMutasiBarangJadi(ReportPeriod $period): void
    {
        $this->ensureDraftType($period, ReportPeriod::TYPE_MUTASI_BARANG_JADI);

        $start = Carbon::create($period->periode_tahun, $period->periode_bulan, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $productIds = DB::table('mfg_work_orders')->whereNotNull('product_id')->distinct()->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get();

        DB::transaction(function () use ($period, $products, $startDate, $endDate): void {
            foreach ($products as $product) {
                $query = DB::table('inventory_ledgers')->where('product_id', $product->id);
                $hasMovement = (clone $query)->where('transaction_date', '<=', $endDate)->exists();
                if (! $hasMovement) {
                    continue;
                }

                $this->upsertMutasiLine(
                    $period,
                    $product->sku,
                    $product->name,
                    $product->unit,
                    (clone $query)->where('transaction_date', '<', $startDate)->latest('transaction_date')->latest('id')->value('running_qty') ?? 0,
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'IN')->sum('qty'),
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'OUT')->sum('qty'),
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'ADJ')->sum('qty'),
                    (clone $query)->where('transaction_date', '<=', $endDate)->latest('transaction_date')->latest('id')->value('running_qty') ?? 0,
                    'Auto-populate dari inventory_ledgers'
                );
            }
        });
    }

    /** @return array{grey_fabric: \Illuminate\Support\Collection, fabric: \Illuminate\Support\Collection, cutting: \Illuminate\Support\Collection, finishing: \Illuminate\Support\Collection} */
    public function rejectAssistData(int $bulan, int $tahun): array
    {
        $start = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return [
            'grey_fabric' => DB::table('mfg_grey_fabric_receipts')->whereBetween('receipt_date', [$startDate, $endDate])->where('qty_rejected', '>', 0)->select('receipt_number', 'receipt_date', 'fabric_id', 'qty_rejected', 'remarks')->get(),
            'fabric' => DB::table('mfg_fabric_receipts')->whereBetween('receipt_date', [$startDate, $endDate])->where('qty_rejected', '>', 0)->select('receipt_number', 'receipt_date', 'fabric_id', 'qty_rejected', 'remarks')->get(),
            'cutting' => DB::table('mfg_cutting_checks')->whereBetween('check_date', [$startDate, $endDate])->where(fn ($query) => $query->where('pieces_rejected', '>', 0)->orWhere('fabric_wastage_kg', '>', 0))->select('check_date', 'pieces_rejected', 'fabric_wastage_kg', 'remarks')->get(),
            'finishing' => DB::table('mfg_finishing_stages')->whereBetween('stage_date', [$startDate, $endDate])->where('pieces_rejected', '>', 0)->select('stage', 'stage_date', 'pieces_rejected', 'remarks')->get(),
        ];
    }

    private function populateMutasiFromLedger(ReportPeriod $period, string $table, string $idColumn, callable $identity, array $groupColumns): void
    {
        $start = Carbon::create($period->periode_tahun, $period->periode_bulan, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $items = DB::table($table)->select([...$groupColumns, $idColumn])->where('transaction_date', '<=', $endDate)->distinct()->get();

        DB::transaction(function () use ($period, $table, $idColumn, $identity, $groupColumns, $items, $startDate, $endDate): void {
            foreach ($items as $item) {
                $query = DB::table($table)->where($idColumn, $item->{$idColumn});
                foreach ($groupColumns as $column) {
                    $query->where($column, $item->{$column});
                }
                [$code, $name, $unit] = $identity($item);
                $saldoAwal = (clone $query)->where('transaction_date', '<', $startDate)->latest('transaction_date')->latest('id')->value('running_qty') ?? 0;
                $saldoAkhir = (clone $query)->where('transaction_date', '<=', $endDate)->latest('transaction_date')->latest('id')->value('running_qty') ?? $saldoAwal;
                $this->upsertMutasiLine($period, $code, $name, $unit, $saldoAwal,
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'IN')->sum('qty'),
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'OUT')->sum('qty'),
                    (clone $query)->whereBetween('transaction_date', [$startDate, $endDate])->where('type', 'ADJ')->sum('qty'),
                    $saldoAkhir, 'Auto-populate dari mfg_material_ledgers');
            }
        });
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function resolveMaterialIdentity(string $itemType, int $itemId): array
    {
        $item = DB::table($itemType === 'YARN' ? 'mfg_yarns' : 'mfg_fabrics')->find($itemId);
        if (! $item) {
            throw new \RuntimeException("Master material {$itemType} #{$itemId} tidak ditemukan.");
        }

        return $itemType === 'YARN'
            ? [$item->yarn_code, trim($item->yarn_type . ' ' . ($item->color ?? '')), $item->unit]
            : [$item->fabric_code, trim($item->fabric_type . ' ' . ($item->color ?? '')), $item->unit];
    }

    private function upsertMutasiLine(ReportPeriod $period, string $code, string $name, string $unit, mixed $opening, mixed $in, mixed $out, mixed $adjustment, mixed $closing, string $note): void
    {
        MutasiLine::updateOrCreate(['report_period_id' => $period->id, 'kode_barang' => $code], [
            'nama_barang' => $name, 'satuan_barang' => $unit, 'jumlah_barang' => 0,
            'saldo_awal' => $opening, 'jumlah_pemasukan_barang' => $in, 'jumlah_pengeluaran_barang' => $out,
            'penyesuaian_adjustment' => $adjustment, 'saldo_akhir' => $closing,
            'hasil_pencacahan' => 'Belum', 'jumlah_selisih' => 0, 'keterangan' => $note,
        ]);
    }

    private function ensureDraftType(ReportPeriod $period, string $type): void
    {
        if ($period->report_type !== $type || ! $period->isDraft()) {
            throw new \RuntimeException('Auto-populate hanya dapat dilakukan pada periode DRAFT dengan jenis laporan yang sesuai.');
        }
    }
}