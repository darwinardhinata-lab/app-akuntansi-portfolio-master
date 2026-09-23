<?php

namespace Tests\Feature\CustomsReports;

use App\Models\User;
use App\Modules\CustomsReports\Models\DokumenPabeanLine;
use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\PosisiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Feature test modul Laporan Bea Cukai (Fase A).
 *
 * Fokus UTAMA: KETELITIAN ANGKA. Test menempuh jalur nyata
 * HTTP (route -> controller -> maatwebsite/excel -> importer ->
 * IndonesianNumberParser -> DB), sehingga kesalahan parsing angka
 * langsung terlihat pada nilai yang tersimpan.
 *
 * Fixture di tests/Fixtures/CustomsReports/ memakai header PERSIS seperti file
 * export asli CEISA (titik pada "Tgl. Dokumen Pabean", garis miring pada
 * "Penyesuaian/Adjustment", header lama tanpa spasi "Jumlahpemasukan barang").
 */
class ReportPeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL lokal (mis. http://localhost/app-akuntansi-portfolio-master/public)
        // membuat route() menghasilkan URL ber-subfolder sehingga request test 404.
        URL::forceRootUrl('http://localhost');
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function makePeriod(
        string $type = ReportPeriod::TYPE_PEMASUKAN,
        string $status = ReportPeriod::STATUS_DRAFT,
    ): ReportPeriod {
        return ReportPeriod::create([
            'report_type'   => $type,
            'periode_bulan' => 9,
            'periode_tahun' => 2026,
            'status'        => $status,
        ]);
    }

    private function fixturePath(string $name): string
    {
        return base_path('tests/Fixtures/CustomsReports/' . $name);
    }

    /**
     * File upload tiruan dari fixture asli — isinya persis byte demi byte,
     * jadi delimiter ';' dan format angka Indonesia tetap utuh.
     */
    private function uploadFixture(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            file_get_contents($this->fixturePath($name))
        );
    }

    /**
     * Halaman create memakai view yang sama dengan edit; controller wajib
     * memberikan model period baru agar `$period->exists` aman dirender.
     */
    public function test_create_page_renders_with_a_new_period_model(): void
    {
        $this->actingUser();

        $response = $this->get(route('customs-reports.create'));

        $response->assertOk();
        $response->assertSee('Buat Periode Laporan CEISA');
        $response->assertSee('Pilih Jenis Laporan');
        $response->assertSee(ReportPeriod::TYPE_LABELS[ReportPeriod::TYPE_PEMASUKAN]);
    }

    /**
     * Jalur penuh HTTP → importer → parser → DB untuk laporan #1 Pemasukan.
     *
     * Assertion PALING KRITIS: "100.000,0000" harus tersimpan sebagai 100000.0000
     * (bukan 100.0000, yang terjadi bila titik ribuan salah dianggap desimal).
     */
    public function test_import_pemasukan_via_http_parses_indonesian_numbers_exactly(): void
    {
        $this->actingUser();
        $period = $this->makePeriod(ReportPeriod::TYPE_PEMASUKAN);

        $response = $this->post(route('customs-reports.import', $period), [
            'file_excel' => $this->uploadFixture('pemasukan_sample.csv'),
        ]);

        $response->assertRedirect(route('customs-reports.show', $period));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $this->assertSame(2, DokumenPabeanLine::where('report_period_id', $period->id)->count());

        $first = DokumenPabeanLine::where('no_bukti', 'PB-001/2026')->firstOrFail();

        // --- ANGKA (inti ketelitian) ---
        $this->assertSame('100000.0000', $first->nilai);
        $this->assertNotSame('100.0000', $first->nilai, 'CRITICAL: 100.000,0000 tidak boleh jadi 100,0000');
        $this->assertSame(100000.0, (float) $first->nilai);
        $this->assertSame('100.00', $first->jumlah_barang);
        $this->assertSame('110.0000', $first->nilai_faktur_pajak);

        // --- TANGGAL dd/mm/yyyy ---
        $this->assertSame('2026-09-09', $first->tgl_dok_pabean->format('Y-m-d'));
        $this->assertSame('2026-09-09', $first->tgl_bukti->format('Y-m-d'));

        // --- HEADER ASLI CEISA (bertitik / panjang) wajib terbaca ---
        $this->assertSame('BC23', $first->jenis_dok_pabean);
        $this->assertSame('0703000123456', $first->no_pendaftaran_dok_pabean);
        $this->assertSame('PT ABC COTTON', $first->pihak_terkait);
        $this->assertSame('K001', $first->kode_barang);
        $this->assertSame('ROLL', $first->satuan_barang);
        $this->assertSame('USD', $first->mata_uang);
        $this->assertSame('000.000-00.00000000', $first->seri_faktur_pajak);

        $second = DokumenPabeanLine::where('no_bukti', 'PB-002/2026')->firstOrFail();
        $this->assertSame('250500.0000', $second->nilai);
        $this->assertSame('275.5500', $second->nilai_faktur_pajak);
        $this->assertSame('200.00', $second->jumlah_barang);
        $this->assertSame('2026-09-08', $second->tgl_dok_pabean->format('Y-m-d'));

        // Total harus utuh: 100.000,0000 + 250.500,0000 = 350.500,0000
        $this->assertSame(350500.0, (float) $first->nilai + (float) $second->nilai);
    }

    /**
     * Assertion bersama impor mutasi — dipakai varian header "dengan spasi"
     * (export baru) dan "tanpa spasi" (export lama). Keduanya WAJIB identik.
     */
    private function assertMutasiLinesImported(ReportPeriod $period): void
    {
        $lines = MutasiLine::where('report_period_id', $period->id)->get();

        $this->assertCount(2, $lines);

        $kb001 = $lines->firstWhere('kode_barang', 'KB001');
        $this->assertNotNull($kb001);

        // 0,00 adalah nilai SAH — dulu dibuang sebagai "tidak valid", sekarang disimpan.
        $this->assertSame('0.00', $kb001->jumlah_barang);
        $this->assertSame('100.00', $kb001->saldo_awal);
        $this->assertSame('50.00', $kb001->jumlah_pemasukan_barang);
        $this->assertSame('10.00', $kb001->jumlah_pengeluaran_barang);
        // Header "Penyesuaian/Adjustment" (mengandung '/') wajib terbaca.
        $this->assertSame('-2.50', $kb001->penyesuaian_adjustment);
        $this->assertSame('137.50', $kb001->saldo_akhir);
        $this->assertSame('-2.50', $kb001->jumlah_selisih);
        $this->assertSame('Sudah', $kb001->hasil_pencacahan);
        $this->assertSame('Stock opname selesai', $kb001->keterangan);

        $kb002 = $lines->firstWhere('kode_barang', 'KB002');
        $this->assertNotNull($kb002);
        $this->assertSame('0.00', $kb002->jumlah_barang);
        $this->assertSame('0.00', $kb002->jumlah_pemasukan_barang);
        $this->assertSame('25.00', $kb002->jumlah_pengeluaran_barang);
        $this->assertSame('1.75', $kb002->penyesuaian_adjustment);
        $this->assertSame('176.75', $kb002->saldo_akhir);
        $this->assertSame('1.75', $kb002->jumlah_selisih);
        $this->assertSame('Belum', $kb002->hasil_pencacahan);
    }

    public function test_import_mutasi_with_space_headers_parses_every_numeric_column(): void
    {
        $this->actingUser();
        $period = $this->makePeriod(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU);

        $response = $this->post(route('customs-reports.import', $period), [
            'file_excel' => $this->uploadFixture('mutasi_with_space.csv'),
        ]);

        $response->assertRedirect(route('customs-reports.show', $period));
        $response->assertSessionMissing('error');

        $this->assertMutasiLinesImported($period);
    }

    /**
     * File export lama menulis "Jumlahpemasukan barang" (spasi hilang).
     * Hasilnya WAJIB identik dengan varian header yang benar — kalau tidak,
     * laporan bulan lama akan tampak "kosong" (0) tanpa error apa pun.
     */
    public function test_import_mutasi_with_legacy_no_space_header_is_equivalent(): void
    {
        $this->actingUser();
        $period = $this->makePeriod(ReportPeriod::TYPE_MUTASI_BARANG_JADI);

        $response = $this->post(route('customs-reports.import', $period), [
            'file_excel' => $this->uploadFixture('mutasi_no_space.csv'),
        ]);

        $response->assertRedirect(route('customs-reports.show', $period));
        $response->assertSessionMissing('error');

        $this->assertMutasiLinesImported($period);
    }

    /**
     * "1.500" pada kolom Jumlah Barang = 1500 satuan, BUKAN 1,5.
     * Kesalahan di sini membuat laporan mutasi meleset 1000x.
     */
    public function test_import_mutasi_reads_dot_thousands_in_jumlah_barang(): void
    {
        $this->actingUser();
        $period = $this->makePeriod(ReportPeriod::TYPE_MUTASI_BARANG_MODAL);

        $this->post(route('customs-reports.import', $period), [
            'file_excel' => $this->uploadFixture('mutasi_jumlah_ribuan.csv'),
        ])->assertSessionMissing('error');

        $line = MutasiLine::where('report_period_id', $period->id)->firstOrFail();

        $this->assertSame('1500.00', $line->jumlah_barang);
        $this->assertSame('1500.00', $line->jumlah_pemasukan_barang);
        $this->assertSame('1500.00', $line->saldo_akhir);
        $this->assertNotSame('1.50', $line->jumlah_barang);
    }
}
