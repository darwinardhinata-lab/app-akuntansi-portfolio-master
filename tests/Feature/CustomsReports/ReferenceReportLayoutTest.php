<?php

namespace Tests\Feature\CustomsReports;

use App\Models\User;
use App\Modules\CustomsReports\Exports\DokumenPabeanExport;
use App\Modules\CustomsReports\Exports\DokumenPabeanTemplateExport;
use App\Modules\CustomsReports\Exports\MutasiExport;
use App\Modules\CustomsReports\Exports\PosisiExport;
use App\Modules\CustomsReports\Imports\DokumenPabeanImport;
use App\Modules\CustomsReports\Imports\MutasiImport;
use App\Modules\CustomsReports\Imports\PosisiImport;
use App\Modules\CustomsReports\Models\DokumenPabeanLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\ReportLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReferenceReportLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $type): ReportPeriod
    {
        return ReportPeriod::create(['report_type' => $type, 'periode_bulan' => 9,
            'periode_tahun' => 2026, 'status' => 'DRAFT']);
    }

    public function test_incoming_and_outgoing_columns_match_their_distinct_references(): void
    {
        $this->assertSame(['No', 'Jenis', 'No Aju', 'Nomor', 'Tanggal', 'Nomor', 'Tanggal',
            'Pemasok/Pengirim', 'Kode barang', 'Nama barang', 'QTY', 'Unit', 'Bruto', 'Netto',
            'Nilai Barang', 'Currency', 'Harga IDR'], array_values(ReportLayout::columns('PEMASUKAN')));
        $this->assertSame(['No', 'Jenis', 'Nomor', 'Tanggal', 'Nomor', 'Tanggal', 'Penerima',
            'Kode barang', 'Nama barang', 'QTY', 'Unit', 'Bruto', 'Netto', 'Nilai Barang', 'Currency'],
            array_values(ReportLayout::columns('PENGELUARAN')));
    }

    public function test_all_four_mutations_and_wip_have_exact_columns_in_screen_and_export(): void
    {
        foreach (['MUTASI_BAHAN_BAKU', 'MUTASI_BARANG_JADI', 'MUTASI_BARANG_MODAL', 'MUTASI_REJECT'] as $type) {
            $period = $this->period($type);
            $expected = ['No', 'Kode Barang', 'Nama Barang', 'Satuan', 'Saldo Awal', 'Pemasukan',
                'Pengeluaran', 'Penyesuaian', 'Saldo Akhir', 'Stock Opname', 'Selisih', 'Keterangan'];
            $this->assertSame($expected, (new MutasiExport($period))->headings());
            $html = view('customs-reports.partials.table-reference', ['period' => $period, 'lines' => collect()])->render();
            $this->assertStringContainsString('colspan="12"', $html);
            $this->assertStringNotContainsString('>Jumlah<', $html);
        }
        $this->assertSame(['No', 'Kode Barang', 'Nama Barang', 'Satuan', 'Jumlah', 'Keterangan'],
            (new PosisiExport($this->period('WIP')))->headings());
    }

    public function test_new_document_fields_persist_and_render_without_tax_columns(): void
    {
        config(['customs.enabled' => true]);
        $period = $this->period('PEMASUKAN');
        $period->lines()->create(['jenis_dok_pabean' => 'BC 2.3', 'no_aju' => '00002303290620260107000001',
            'no_pendaftaran_dok_pabean' => '000297', 'tgl_dok_pabean' => '2026-09-01',
            'no_bukti' => 'BPB-001', 'tgl_bukti' => '2026-09-02', 'pihak_terkait' => 'Pemasok',
            'kode_barang' => 'MAT-01', 'nama_barang' => 'Kain', 'jumlah_barang' => 5,
            'satuan_barang' => 'MTR', 'mata_uang' => 'JPY', 'nilai' => 100,
            'bruto' => 7, 'netto' => 6, 'harga_idr' => 10000]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->get(route('customs-reports.show', $period))->assertOk()
            ->assertSee('Dokumen Pabean')->assertSee('BPB')->assertSee('No Aju')
            ->assertSee('00002303290620260107000001')->assertSee('7,00')->assertSee('10.000,00')
            ->assertDontSee('Seri FP')->assertDontSee('Nilai FP');
        $this->assertSame('7.0000', $period->lines()->first()->bruto);
    }

    public function test_xlsx_preserves_long_identifiers_and_numeric_values(): void
    {
        $period = $this->period('PEMASUKAN');
        $period->lines()->create(['jenis_dok_pabean' => 'BC 2.3', 'no_aju' => '00002303290620260107000001',
            'no_pendaftaran_dok_pabean' => '000297', 'tgl_dok_pabean' => '2026-09-01',
            'no_bukti' => 'BPB-001', 'tgl_bukti' => '2026-09-02', 'pihak_terkait' => 'Pemasok',
            'kode_barang' => 'MAT-01', 'nama_barang' => '=1+1', 'jumlah_barang' => 5,
            'satuan_barang' => 'MTR', 'mata_uang' => 'IDR', 'nilai' => 100, 'harga_idr' => 100]);
        $path = tempnam(sys_get_temp_dir(), 'bc-layout-');
        try {
            file_put_contents($path, Excel::raw(new DokumenPabeanExport($period), ExcelWriter::XLSX));
            // Export installs a global binder; use the normal reader binder when verifying cell types.
            \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder());
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('00002303290620260107000001', $sheet->getCell('C2')->getValue());
            $this->assertSame('000297', $sheet->getCell('D2')->getValue());
            $this->assertSame('s', $sheet->getCell('J2')->getDataType());
            $this->assertSame('n', $sheet->getCell('K2')->getDataType());
            $this->assertSame(5.0, (float) $sheet->getCell('K2')->getValue());
            $this->assertNull($sheet->getCell('M2')->getValue());
            $this->assertSame('n', $sheet->getCell('Q2')->getDataType());
            $this->assertSame(100.0, (float) $sheet->getCell('Q2')->getValue());
        } finally {
            unlink($path);
        }
    }

    public function test_reference_templates_import_new_fields_and_legacy_imports_remain_supported(): void
    {
        $period = $this->period('PEMASUKAN');
        $row = array_fill_keys((new DokumenPabeanTemplateExport($period))->headings(), '');
        $row = array_merge($row, ['Jenis' => 'BC 2.3', 'No Aju' => '000001',
            'No. Pendaftaran Dokumen Pabean' => '000297', 'Tgl. Dokumen Pabean' => '01/09/2026',
            'No. Bukti Penerimaan Barang' => 'BPB-1', 'Tgl. Bukti' => '02/09/2026',
            'Pemasok/Pengirim' => 'Pemasok', 'QTY' => '5,00', 'Unit' => 'MTR',
            'Nilai Barang' => '100,00', 'Currency' => 'JPY', 'Bruto' => '7,50', 'Netto' => '6,00', 'Harga IDR' => '10.000,00']);
        $line = (new DokumenPabeanImport($period))->model($row);
        $this->assertInstanceOf(DokumenPabeanLine::class, $line);
        $this->assertSame('000001', $line->no_aju);
        $this->assertSame('7.5000', $line->bruto);
        $this->assertSame('10000.0000', $line->harga_idr);
        $this->assertSame('Pemasok', $line->pihak_terkait);
        $mutasi = (new MutasiImport($this->period('MUTASI_BAHAN_BAKU')))->model([
            'Kode Barang' => 'M1', 'Satuan' => 'KGS', 'Saldo Awal' => '2,00',
            'Pemasukan' => '3,00', 'Pengeluaran' => '1,00', 'Saldo Akhir' => '4,00', 'Stock Opname' => '4,00']);
        $this->assertSame('3.00', $mutasi->jumlah_pemasukan_barang);
        $this->assertSame('KGS', $mutasi->satuan_barang);
        $wip = (new PosisiImport($this->period('WIP')))->model(['Kode Barang' => 'W1', 'Satuan' => 'PCS', 'Jumlah' => '12,00']);
        $this->assertSame('12.00', $wip->jumlah_barang);
        $this->assertNull(ReportLayout::value($mutasi->fill(['hasil_pencacahan' => 'Belum']), 'hasil_pencacahan', 1));
    }
}