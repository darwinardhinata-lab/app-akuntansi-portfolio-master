<?php

namespace Tests\Unit\CustomsReports;

use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Tests\TestCase;

/**
 * Unit test kritis untuk IndonesianNumberParser.
 *
 * Ini adalah uji ketelitian tersendiri — salah parse angka berarti
 * data laporan ke Bea Cukai salah nilai.
 */
class IndonesianNumberParserTest extends TestCase
{
    /**
     * Kasus PERSIS dari data asli: "100.000,0000" harus jadi 100000.0
     * (bukan 100.0 yang terjadi jika titik salah dihapus).
     */
    public function test_parse_indonesian_format_thousands_and_decimal(): void
    {
        $result = IndonesianNumberParser::parse('100.000,0000');

        $this->assertEquals(100000.0, $result);
        $this->assertNotEquals(100.0, $result, 'CRITICAL: 100.000,0000 must NOT parse to 100.0');
    }

    public function test_parse_simple_decimal_comma(): void
    {
        $result = IndonesianNumberParser::parse('100,00');

        $this->assertEquals(100.0, $result);
    }

    public function test_parse_zero_with_decimals(): void
    {
        $result = IndonesianNumberParser::parse('0,0000');

        $this->assertEquals(0.0, $result);
    }

    /**
     * 0 (nol) adalah nilai SAH dan TIDAK boleh tertukar dengan null.
     * Importer memakai guard `=== null`, bukan falsy — jadi beda ini krusial:
     * kalau parser mengembalikan null untuk "0,00", baris mutasi asli
     * (kolom Jumlah Barang sering 0,00) akan ikut dibuang.
     */
    public function test_parse_zero_is_not_null(): void
    {
        $this->assertNotNull(IndonesianNumberParser::parse('0'));
        $this->assertNotNull(IndonesianNumberParser::parse('0,00'));
        $this->assertSame(0.0, IndonesianNumberParser::parse('0'));
        $this->assertSame(0.0, IndonesianNumberParser::parse('0,00'));

        // Hanya input kosong/tak terbaca yang null.
        $this->assertNull(IndonesianNumberParser::parse(''));
        $this->assertNull(IndonesianNumberParser::parse('TIDAK ADA'));
    }

    public function test_parse_negative_indonesian_number(): void
    {
        // Kolom Penyesuaian/Adjustment bisa negatif (mis. koreksi stok opname).
        $this->assertSame(-2500.5, IndonesianNumberParser::parse('-2.500,50'));
        $this->assertSame(-2.5, IndonesianNumberParser::parse('-2,50'));
    }

    /**
     * Kasus AMBIGU yang paling berbahaya: string dengan HANYA titik.
     *
     * "15.5" bisa berarti desimal angka mentah Excel (sel numerik dibaca float
     * → di-cast ke string) ATAU ribuan gaya Indonesia. Aturan yang disepakati:
     * dianggap ribuan HANYA jika polanya benar-benar kelompok 3 digit dan
     * angka depannya bukan 0 — sehingga "1.500"→1500, "15.5"→15.5,
     * "0.125"→0.125 (bukan 125!).
     */
    public function test_parse_dot_only_ambiguity_rules(): void
    {
        // Kelompok ribuan 3 digit → ribuan
        $this->assertSame(1500.0, IndonesianNumberParser::parse('1.500'));
        $this->assertSame(12345678.0, IndonesianNumberParser::parse('12.345.678'));
        // Desimal mentah (digit desimal ≠ 3) → desimal apa adanya
        $this->assertSame(15.5, IndonesianNumberParser::parse('15.5'));
        $this->assertSame(0.125, IndonesianNumberParser::parse('0.125'));
        $this->assertSame(1.25, IndonesianNumberParser::parse('1.25'));
    }

    public function test_parse_indonesian_number_without_thousand_separator(): void
    {
        // Excel sering membuang pemisah ribuan pada sel numerik.
        $this->assertSame(1250.0, IndonesianNumberParser::parse('1250,00'));
        $this->assertSame(1250.0, IndonesianNumberParser::parse('1250.00'));
    }

    public function test_parse_large_indonesian_number(): void
    {
        $result = IndonesianNumberParser::parse('1.500.000,50');

        $this->assertEquals(1500000.5, $result);
        $this->assertNotEquals(1500.0, $result);
    }

    public function test_parse_integer_without_separator(): void
    {
        $result = IndonesianNumberParser::parse('5000');

        $this->assertEquals(5000.0, $result);
    }

    public function test_parse_decimal_only_comma(): void
    {
        $result = IndonesianNumberParser::parse('1,5');

        $this->assertEquals(1.5, $result);
    }

    public function test_parse_empty_or_null_returns_null(): void
    {
        $this->assertNull(IndonesianNumberParser::parse(''));
        $this->assertNull(IndonesianNumberParser::parse('  '));
        $this->assertNull(IndonesianNumberParser::parse(null));
    }

    public function test_parse_invalid_string_returns_null(): void
    {
        $this->assertNull(IndonesianNumberParser::parse('abc'));
        $this->assertNull(IndonesianNumberParser::parse('NaN'));
    }

    public function test_parse_date_indonesian_format(): void
    {
        $result = IndonesianNumberParser::parseDate('09/09/2026');

        $this->assertEquals('2026-09-09', $result);
    }

    public function test_parse_date_empty_or_null_returns_null(): void
    {
        $this->assertNull(IndonesianNumberParser::parseDate(''));
        $this->assertNull(IndonesianNumberParser::parseDate(null));
    }

    public function test_parse_date_excel_serial_and_month_name_format(): void
    {
        // Serial sel tanggal .xls: 46235 = 2026-08-01, 45658 = 2025-01-01
        $this->assertSame('2026-08-01', IndonesianNumberParser::parseDate('46235'));
        $this->assertSame('2025-01-01', IndonesianNumberParser::parseDate('45658'));

        // Singkatan bulan bertitik (pola export PDF CEISA)
        $this->assertSame('2026-09-01', IndonesianNumberParser::parseDate('1 Sep. 2026'));
        $this->assertSame('2026-09-01', IndonesianNumberParser::parseDate('1 Sept 2026'));

        // Angka yang bukan serial tanggal wajar → null, bukan tanggal ngawur
        $this->assertNull(IndonesianNumberParser::parseDate('20260909'));
        $this->assertNull(IndonesianNumberParser::parseDate('12345'));
    }

    public function test_parse_date_invalid_returns_null(): void
    {
        $this->assertNull(IndonesianNumberParser::parseDate('invalid-date'));
        // Tanggal yang tidak ada di kalender HARUS ditolak, bukan digulung
        // menjadi 2027-02-01 (perilaku bawaan DateTime/Carbon).
        $this->assertNull(IndonesianNumberParser::parseDate('32/13/2026'));
        $this->assertNull(IndonesianNumberParser::parseDate('31/02/2026'));
        $this->assertNull(IndonesianNumberParser::parseDate('30/02/2024'));
        $this->assertNull(IndonesianNumberParser::parseDate('00/00/2026'));
        // Tanggal kabisat yang sah tetap diterima.
        $this->assertEquals('2024-02-29', IndonesianNumberParser::parseDate('29/02/2024'));
    }

    /**
     * Sel tanggal pada file .xls dibaca maatwebsite/excel sebagai angka
     * (serial Excel), bukan string dd/mm/yyyy — jadi parser harus menanganinya.
     */
    public function test_parse_date_excel_serial(): void
    {
        // 45658 = 2025-01-01 pada sistem tanggal Excel 1900.
        $this->assertEquals('2025-01-01', IndonesianNumberParser::parseDate('45658'));
        $this->assertEquals('2025-01-01', IndonesianNumberParser::parseDate('45658.0'));

        // Angka di luar rentang tahun wajar TIDAK boleh jadi tanggal
        // (mis. nomor pendaftaran 20260909).
        $this->assertNull(IndonesianNumberParser::parseDate('20260909'));
        $this->assertNull(IndonesianNumberParser::parseDate('123'));
    }

    public function test_parse_date_iso_format(): void
    {
        $this->assertEquals('2026-09-09', IndonesianNumberParser::parseDate('2026-09-09'));
    }

    public function test_parse_date_with_dash_separator(): void
    {
        $this->assertEquals('2026-09-09', IndonesianNumberParser::parseDate('9-9-2026'));
        $this->assertEquals('2026-09-09', IndonesianNumberParser::parseDate('09-09-2026'));
    }

    /**
     * Kasus ambigu: hanya titik. Kelompok 3 digit = ribuan Indonesia,
     * sedangkan "15.5"/"0.125" adalah desimal angka mentah Excel.
     */
    public function test_parse_ambiguous_dot_only_values(): void
    {
        $this->assertSame(1500.0, IndonesianNumberParser::parse('1.500'));
        // '1.500.000' = 1,5 juta. Ekspektasi sebelumnya (1500.0) SALAH dan
        // sudah dikoreksi: pola kelompok-ribuan 3 digit penuh wajib dijumlahkan.
        $this->assertSame(1500000.0, IndonesianNumberParser::parse('1.500.000'));
        $this->assertSame(15.5, IndonesianNumberParser::parse('15.5'));
        $this->assertSame(0.125, IndonesianNumberParser::parse('0.125'));
        $this->assertSame(100000.0, IndonesianNumberParser::parse('100.000'));
    }

    public function test_format_indonesian_number(): void
    {
        $result = IndonesianNumberParser::format(100000.0, 2);

        $this->assertEquals('100.000,00', $result);
    }

    public function test_format_with_decimals(): void
    {
        $result = IndonesianNumberParser::format(1500000.5, 2);

        $this->assertEquals('1.500.000,50', $result);
    }

    public function test_format_null_returns_empty(): void
    {
        $this->assertEquals('', IndonesianNumberParser::format(null));
    }

    /**
     * Spasi (termasuk non-breaking space dari export HTML/Excel) juga
     * muncul sebagai pemisah ribuan di file asli → harus dibuang.
     */
    public function test_parse_space_as_thousands_separator(): void
    {
        $this->assertSame(1500.5, IndonesianNumberParser::parse('1 500,50'));
        $this->assertSame(1500.5, IndonesianNumberParser::parse("1\u{00A0}500,50"));
    }

    /**
     * Batas dua kasus "hanya titik" yang paling rawan:
     * "1.500" = ribuan (1500) sedangkan "15.5" = desimal (15.5).
     * Kalau aturan ini salah, nilai laporan bisa meleset 10x/1000x.
     */
    public function test_parse_dot_only_disambiguation(): void
    {
        // Kelompok 3 digit tanpa angka depan 0 → ribuan.
        $this->assertSame(1500.0, IndonesianNumberParser::parse('1.500'));
        $this->assertSame(100000.0, IndonesianNumberParser::parse('100.000'));
        $this->assertSame(12345678.0, IndonesianNumberParser::parse('12.345.678'));

        // Sisanya (desimal 1 digit, angka depan 0) → titik adalah desimal.
        $this->assertSame(15.5, IndonesianNumberParser::parse('15.5'));
        $this->assertSame(0.125, IndonesianNumberParser::parse('0.125'));
    }

    public function test_parse_date_excel_serial_precise_offsets(): void
    {
        // Serial 46274 = 2026-09-09, 46273 = sehari sebelumnya.
        $this->assertSame('2026-09-09', IndonesianNumberParser::parseDate('46274'));
        $this->assertSame('2026-09-08', IndonesianNumberParser::parseDate('46273'));
    }

    /**
     * String angka 8 digit (mis. nomor pendaftaran "20260909") BUKAN tanggal
     * — harus ditolak, bukan diam-diam jadi tahun 20260909.
     */
    public function test_parse_date_rejects_non_date_number(): void
    {
        $this->assertNull(IndonesianNumberParser::parseDate('20260909'));
        $this->assertNull(IndonesianNumberParser::parseDate('999999999'));
    }

    public function test_parse_date_iso_and_indonesian_month_name(): void
    {
        $this->assertSame('2026-09-09', IndonesianNumberParser::parseDate('2026-09-09'));
        $this->assertSame('2026-09-01', IndonesianNumberParser::parseDate('1 September 2026'));
        $this->assertSame('2026-09-01', IndonesianNumberParser::parseDate('1 Sep 2026'));
    }

    /**
     * Tahun di luar rentang wajar 1990..2100 harus null (bukan diterima).
     */
    public function test_parse_date_out_of_range_year_returns_null(): void
    {
        $this->assertNull(IndonesianNumberParser::parseDate('01/01/1899'));
        $this->assertNull(IndonesianNumberParser::parseDate('01/01/2200'));
    }
}
