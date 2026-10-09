<?php

namespace Tests\Feature\CustomsReports;

use App\Models\User;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\InternalReportSyncService;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualDraftProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_manual_rows_survive_read_export_sync_and_finalize(): void
    {
        config(['customs.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $period = ReportPeriod::create(['report_type' => 'PEMASUKAN', 'periode_bulan' => 9, 'periode_tahun' => 2026, 'status' => 'DRAFT']);
        $line = $period->lines()->create(['jenis_dok_pabean' => 'BC 2.3', 'no_pendaftaran_dok_pabean' => 'MANUAL-REG',
            'tgl_dok_pabean' => '2026-09-01', 'no_bukti' => 'MANUAL', 'tgl_bukti' => '2026-09-01',
            'pihak_terkait' => 'Supplier', 'kode_barang' => 'ITEM', 'nama_barang' => 'Manual',
            'jumlah_barang' => 2, 'satuan_barang' => 'PCS', 'mata_uang' => 'IDR', 'nilai' => 50]);
        $before = $line->fresh()->getAttributes();
        $this->get(route('customs-reports.show', $period))->assertOk();
        $this->get(route('customs-reports.export', $period))->assertOk();
        app(InternalReportSyncService::class)->sync($period);
        app(ReportPeriodService::class)->finalize($period);
        $this->assertSame($before, $line->fresh()?->getAttributes());
        $this->assertSame('FINAL', $period->fresh()->status);
    }

    public function test_manual_csv_import_switches_internal_source_and_survives_redirect_and_finalize(): void
    {
        config(['customs.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $period = app(ReportPeriodService::class)->createDraft('PEMASUKAN', 9, 2026);
        $this->assertSame('INTERNAL', $period->source_mode);
        $csv = "jenis_dok_pabean;no_pendaftaran_dok_pabean;tgl_dok_pabean;no_bukti;tgl_bukti;pihak_terkait;kode_barang;nama_barang;jumlah_barang;satuan_barang;mata_uang;nilai\n"
            ."BC 2.3;REG-IMPORT;2026-09-01;DOC-IMPORT;2026-09-01;Supplier;ITEM;Manual;2;PCS;IDR;50\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('bc.csv', $csv);
        $this->post(route('customs-reports.import', $period), ['file_excel' => $file])->assertRedirect(route('customs-reports.show', $period));
        $this->assertSame('MANUAL', $period->fresh()->source_mode);
        $line = $period->lines()->firstOrFail();
        $before = $line->getAttributes();
        $this->get(route('customs-reports.show', $period))->assertOk();
        $this->get(route('customs-reports.export', $period))->assertOk();
        $this->post(route('customs-reports.populate-mutasi', $period))->assertSessionHas('error');
        $this->post(route('customs-reports.finalize', $period))->assertSessionHas('success');
        $this->assertSame($before, $line->fresh()->getAttributes());
    }

    public function test_all_importers_protect_source_and_recheck_persisted_final_status(): void
    {
        foreach ([\App\Modules\CustomsReports\Imports\DokumenPabeanImport::class => 'PEMASUKAN',
            \App\Modules\CustomsReports\Imports\MutasiImport::class => 'MUTASI_BARANG_JADI',
            \App\Modules\CustomsReports\Imports\PosisiImport::class => 'WIP'] as $class => $type) {
            $period = ReportPeriod::create(['report_type' => $type, 'periode_bulan' => 9, 'periode_tahun' => 2026,
                'status' => 'DRAFT', 'source_mode' => 'INTERNAL']);
            $import = new $class($period);
            $import->model([]);
            $this->assertSame('MANUAL', $period->fresh()->source_mode);
            $period->fresh()->update(['status' => 'FINAL']);
            try {
                $import->model([]);
                $this->fail('Stale draft must not bypass FINAL guard.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('DRAFT', $e->getMessage());
            }
        }
    }
}