<?php

namespace App\Modules\CustomsReports\Support;

/**
 * IndonesianNumberParser — Parser angka & tanggal format Indonesia.
 *
 * Ini file paling kritis untuk akurasi laporan Bea Cukai: salah parse =
 * nilai yang dilaporkan ke CEISA salah.
 *
 * Format angka Indonesia:
 *   100.000,0000  → titik pemisah ribuan, koma pemisah desimal  → 100000.0
 *   1.500         → titik pemisah ribuan (tanpa desimal)        → 1500.0
 *   100,00        → koma pemisah desimal                        → 100.0
 *   5000          → tanpa pemisah                               → 5000.0
 *
 * KASUS AMBIGU (penting): string dengan HANYA titik, mis. "15.5".
 *   - Bisa berarti ribuan Indonesia ("15.500" → 15500)
 *   - Bisa berarti desimal angka mentah Excel: sel numerik dibaca float
 *     oleh maatwebsite/excel, lalu di-cast ke string → "15.5"
 *   Aturan yang dipakai: dianggap RIBUAN hanya jika polanya benar-benar
 *   kelompok 3 digit dan tidak diawali 0
 *   ("1.500" → 1500, tetapi "15.5" dan "0.125" → desimal).
 */
class IndonesianNumberParser
{
    /** Epoch serial tanggal Excel (sistem 1900). */
    private const EXCEL_EPOCH = '1899-12-30';

    /**
     * Rentang serial Excel yang masih wajar sebagai tanggal:
     * 1990-01-01 (32874) s/d 2100-01-01 (73051). Dipakai agar angka lain
     * (mis. "20260909") tidak salah diterjemahkan menjadi tanggal.
     */
    private const EXCEL_SERIAL_MIN = 32874;
    private const EXCEL_SERIAL_MAX = 73051;

    /** Rentang tahun laporan yang dianggap wajar. */
    private const YEAR_MIN = 1990;
    private const YEAR_MAX = 2100;

    /**
     * Nama bulan (Indonesia + Inggris, lengkap & singkatan) → nomor bulan.
     * Dipakai untuk input seperti '1 September 2026' / '1 Sep 2026'.
     */
    private const MONTH_NAMES = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'ags' => 8, 'agu' => 8, 'august' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'okt' => 10, 'october' => 10, 'oct' => 10,
        'november' => 11, 'nopember' => 11, 'nop' => 11, 'nov' => 11,
        'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
    ];

    /**
     * Parse angka format Indonesia ke float.
     *
     * @param string|null $value
     * @return float|null  null jika input kosong atau tidak valid
     */
    public static function parse(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        // Spasi & non-breaking space juga dipakai sebagai pemisah ribuan pada
        // export HTML/Excel CEISA (mis. "1 500,50" / "1\u{00A0}500,50").
        // Dibuang lebih dulu agar sisa logika hanya berurusan dengan '.' dan ','.
        $text = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $text) ?? '';

        if ($text === '') {
            return null;
        }

        $hasDot   = str_contains($text, '.');
        $hasComma = str_contains($text, ',');

        if ($hasDot && $hasComma) {
            // Keduanya ada → pemisah yang muncul TERAKHIR adalah desimal.
            // "100.000,0000" → desimal koma  → 100000.0000
            // "1,500.25"     → desimal titik → 1500.25
            $decimalSeparator = strrpos($text, '.') > strrpos($text, ',') ? '.' : ',';
        } elseif ($hasComma) {
            // Hanya koma → desimal koma, tanpa pemisah ribuan. "100,00" → 100.0
            $decimalSeparator = ',';
        } elseif ($hasDot && preg_match('/^[+-]?[1-9]\d{0,2}(\.\d{3})+$/', $text) === 1) {
            // Hanya titik & polanya kelompok ribuan 3 digit → titik adalah ribuan.
            // "1.500" → 1500 | "100.000" → 100000 | "12.345.678" → 12345678
            $decimalSeparator = ',';
        } else {
            // Sisanya (tanpa pemisah, atau titik desimal angka mentah Excel
            // seperti "15.5" / "0.125") → titik dipakai sebagai desimal apa adanya.
            $decimalSeparator = '.';
        }

        $thousandsSeparator = $decimalSeparator === '.' ? ',' : '.';

        $clean = str_replace($thousandsSeparator, '', $text);
        $clean = str_replace($decimalSeparator, '.', $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Parse tanggal format Indonesia → 'Y-m-d'.
     *
     * Mendukung 4 bentuk input yang nyata muncul di file laporan CEISA:
     *   1. '2026-09-09'               → ISO
     *   2. '09/09/2026' / '9-9-2026'  → dd/mm/yyyy (Indonesia)
     *   3. '1 September 2026'         → nama bulan
     *   4. 45658 / '45658'            → serial tanggal Excel (sel tanggal pada
     *      .xls dibaca sebagai angka oleh maatwebsite/excel)
     *
     * Validasi kalender memakai checkdate() — TIDAK cukup
     * DateTime/Carbon::createFromFormat(), karena keduanya "menggulung"
     * tanggal tidak valid ('32/13/2026' → 2027-02-01) alih-alih menolaknya.
     *
     * @param string|null $value
     * @return string|null  null jika tidak valid
     */
    public static function parseDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim($value);

        if ($text === '') {
            return null;
        }

        // 1. ISO yyyy-mm-dd
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m) === 1) {
            return self::buildDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 2. dd/mm/yyyy (menerima pemisah '-' dan '.' juga)
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $text, $m) === 1) {
            return self::buildDate((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 3. '1 September 2026' / '1 Sep 2026' — bulan ditulis dengan nama
        //    (muncul pada export HTML/PDF CEISA dan entri manual).
        if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\.?\s+(\d{4})$/', $text, $m) === 1) {
            $month = self::MONTH_NAMES[strtolower($m[2])] ?? null;

            return $month === null ? null : self::buildDate((int) $m[3], $month, (int) $m[1]);
        }

        // 4. Serial tanggal Excel — hanya jika angkanya berada dalam rentang
        //    tahun yang wajar, supaya angka lain tidak salah jadi tanggal.
        if (preg_match('/^\d+(\.\d+)?$/', $text) === 1) {
            $serial = (int) floor((float) $text);

            if ($serial >= self::EXCEL_SERIAL_MIN && $serial <= self::EXCEL_SERIAL_MAX) {
                return (new \DateTimeImmutable(self::EXCEL_EPOCH))
                    ->modify("+{$serial} days")
                    ->format('Y-m-d');
            }

            return null;
        }

        return null;
    }

    /**
     * Format float ke string angka Indonesia (untuk export).
     *
     * @param float|null $value
     * @param int $decimalPlaces
     * @return string
     */
    public static function format(?float $value, int $decimalPlaces = 2): string
    {
        if ($value === null) {
            return '';
        }

        return number_format((float) $value, $decimalPlaces, ',', '.');
    }

    /**
     * Validasi kalender lalu susun 'Y-m-d'.
     * Mengembalikan null untuk tanggal yang tidak ada (mis. 31/02/2026).
     */
    private static function buildDate(int $year, int $month, int $day): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        // Tahun di luar rentang wajar ditolak supaya angka acak tidak
        // diam-diam menjadi tanggal (mis. '01/01/2200').
        if ($year < self::YEAR_MIN || $year > self::YEAR_MAX) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
