<?php

namespace App\Modules\CustomsReports\Imports;

use App\Modules\CustomsReports\Models\DokumenPabeanLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Import laporan dokumen pabean (#1 Pemasukan, #2 Pengeluaran).
 *
 * Format file sesuai 100940.xls / 101029.xls.
 * Heading row dinormalisasi agar toleran terhadap variasi kapitalisasi/spasi.
 * Angka format Indonesia WAJIB di-parse dengan IndonesianNumberParser.
 */
class DokumenPabeanImport extends StringValueBinder implements ToModel, WithHeadingRow, WithCustomCsvSettings, WithCustomValueBinder
{
    private ReportPeriod $reportPeriod;
    private array $errors = [];
    private int $successCount = 0;

    public function __construct(ReportPeriod $reportPeriod)
    {
        $this->reportPeriod = $reportPeriod;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => ';'];
    }

    public function model(array $row): ?DokumenPabeanLine
    {
        \App\Modules\CustomsReports\Support\ManualReportImport::protect($this->reportPeriod);
        // Guard: tolak import jika periode sudah FINAL / DIUNGGAH
        if ($this->reportPeriod->isFinal() || $this->reportPeriod->isUploaded()) {
            throw new \RuntimeException(
                'Periode laporan sudah FINAL/DIUNGGAH. Import tidak diperbolehkan.'
            );
        }

        $normalized = $this->normalizeRow($row);

        // Parse tanggal — keduanya wajib valid
        $tglDokPabean = IndonesianNumberParser::parseDate(
            $this->col($normalized, 'tgl_dok_pabean', 'tgldokpabean', 'tgl_dokumen_pabean')
        );
        if (! $tglDokPabean) {
            $this->errors[] = "Baris {$this->nextRowNumber()}: tgl_dok_pabean tidak valid, dilewati.";
            return null;
        }

        $tglBukti = IndonesianNumberParser::parseDate(
            $this->col($normalized, 'tgl_bukti', 'tglbukti')
        );
        if (! $tglBukti) {
            $this->errors[] = "Baris {$this->nextRowNumber()}: tgl_bukti tidak valid, dilewati.";
            return null;
        }

        // Parse angka format Indonesia
        $jumlahBarang = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_barang', 'jumlahbarang', 'qty')
        ) ?? 0;

        $nilai = IndonesianNumberParser::parse(
            $this->col($normalized, 'nilai', 'nilai_barang')
        );

        // Nilai 0,0000 adalah nilai SAH (bukan invalid) — hanya null (kosong/tak terbaca) yang dilewati.
        if ($nilai === null) {
            $this->errors[] = "Baris {$this->nextRowNumber()}: nilai tidak valid, dilewati.";
            return null;
        }

        $nilaiFaktur = IndonesianNumberParser::parse(
            $this->col($normalized, 'nilai_faktur_pajak', 'nilaifakturpajak')
        );

        $line = new DokumenPabeanLine([
            'report_period_id'         => $this->reportPeriod->id,
            'jenis_dok_pabean'         => $this->col($normalized, 'jenis_dok_pabean', 'jenis_dokumen_pabean', 'jenis'),
            'no_aju'                  => $this->col($normalized, 'no_aju'),
            'bruto'                   => IndonesianNumberParser::parse($this->col($normalized, 'bruto')),
            'netto'                   => IndonesianNumberParser::parse($this->col($normalized, 'netto')),
            'harga_idr'               => IndonesianNumberParser::parse($this->col($normalized, 'harga_idr')),
            'no_pendaftaran_dok_pabean' => $this->col($normalized, 'no_pendaftaran_dok_pabean', 'no_pendaftaran_dokumen_pabean'),
            'tgl_dok_pabean'           => $tglDokPabean,
            'no_bukti'                 => $this->col($normalized, 'no_bukti', 'no_bukti_penerimaan_barang', 'no_bukti_pengeluaran'),
            'tgl_bukti'                => $tglBukti,
            'pihak_terkait'            => $this->col($normalized, 'pihak_terkait', 'pengirim_barang', 'penerima_barang', 'pemasok_pengirim', 'penerima'),
            'kode_barang'              => $this->col($normalized, 'kode_barang', 'kodebarang'),
            'nama_barang'              => $this->col($normalized, 'nama_barang', 'namabarang'),
            'jumlah_barang'            => $jumlahBarang,
            'satuan_barang'            => $this->col($normalized, 'satuan_barang', 'satuanbarang', 'unit'),
            'mata_uang'                => $this->col($normalized, 'mata_uang', 'matauang', 'currency'),
            'nilai'                    => $nilai,
            'seri_faktur_pajak'        => $this->col($normalized, 'seri_faktur_pajak', 'serifakturpajak', 'seri_faktur_pajak') ?: null,
            'nilai_faktur_pajak'       => $nilaiFaktur,
        ]);

        $this->successCount++;
        return $line;
    }

    /**
     * Normalisasi row key: lowercase, hapus semua karakter non-alfanumerik.
     * Ini memastikan "Jumlahpemasukan barang" dan "Jumlah Pemasukan Barang"
     * menghasilkan key yang sama setelah normalisasi.
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normKey = preg_replace('/[^a-z0-9]+/', '', strtolower($key));
                $normalized[$normKey] = $value;
            }
        }
        return $normalized;
    }

    /**
     * Nomor baris berikutnya yang akan dilaporkan pada pesan error.
     * Dibuat method terpisah karena PHP tidak mengizinkan aritmatika
     * di dalam interpolasi string ("{$a + $b}" = syntax error).
     */
    private function nextRowNumber(): int
    {
        return $this->successCount + count($this->errors) + 1;
    }

    /**
     * Resolver kolom fleksibel — mencocokkan beberapa variasi header
     * ke satu kolom DB.
     */
    private function col(array $row, string ...$aliases): ?string
    {
        // Tambahkan varian normal: hapus spasi/underscore dari setiap alias
        foreach ($aliases as $alias) {
            $normAlias = preg_replace('/[^a-z0-9]+/', '', strtolower($alias));
            if (isset($row[$normAlias]) && $row[$normAlias] !== null && $row[$normAlias] !== '') {
                return (string) $row[$normAlias];
            }
        }
        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}