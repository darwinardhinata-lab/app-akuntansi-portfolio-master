<?php

namespace App\Modules\CustomsReports\Imports;

use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Import laporan mutasi (#3 Bahan Baku, #5 Barang Jadi, #6 Barang Modal, #7 Reject).
 *
 * Format file sesuai 101037.xls / 101116.xls / 101139.xls / 101147.xls.
 * Dipakai ulang untuk keempat jenis laporan — perbedaan ditentukan oleh
 * report_period_id → report_type induknya.
 *
 * Toleran terhadap variasi header: "Jumlah Pemasukan Barang" vs "Jumlahpemasukan barang"
 * (spasi hilang pada export lama) — normalisasi key memastikan keduanya cocok.
 */
class MutasiImport extends StringValueBinder implements ToModel, WithHeadingRow, WithCustomCsvSettings, WithCustomValueBinder
{
    private ReportPeriod $reportPeriod;
    private array $rowErrors = [];
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

    public function model(array $row): ?MutasiLine
    {
        \App\Modules\CustomsReports\Support\ManualReportImport::protect($this->reportPeriod);
        // Guard: tolak import jika periode sudah FINAL / DIUNGGAH
        if ($this->reportPeriod->isFinal() || $this->reportPeriod->isUploaded()) {
            throw new \RuntimeException(
                'Periode laporan sudah FINAL/DIUNGGAH. Import tidak diperbolehkan.'
            );
        }

        $normalized = $this->normalizeRow($row);

        $rowNumber = $this->successCount + count($this->rowErrors) + 1;

        // Parse semua angka dengan IndonesianNumberParser
        $jumlahBarang = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_barang', 'jumlahbarang')
        );
        // The reference layout has no redundant Jumlah column; retain legacy validation if supplied.
        if ($this->col($normalized, 'jumlah_barang', 'jumlahbarang') === null
            && $this->col($normalized, 'saldo_awal', 'saldoawal') !== null) {
            $jumlahBarang = 0;
        }
        // 0,00 adalah nilai SAH (data export lama memang 0) — hanya null yang dilewati.
        if ($jumlahBarang === null) {
            $this->rowErrors[] = "Baris {$rowNumber}: jumlah_barang tidak valid, dilewati.";
            return null;
        }

        $saldoAwal = IndonesianNumberParser::parse(
            $this->col($normalized, 'saldo_awal', 'saldoawal')
        ) ?? 0;

        $jumlahPemasukan = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_pemasukan_barang', 'jumlahpemasukan_barang', 'pemasukan')
        ) ?? 0;

        $jumlahPengeluaran = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_pengeluaran_barang', 'jumlahpengeluaranbarang', 'pengeluaran')
        ) ?? 0;

        $penyesuaian = IndonesianNumberParser::parse(
            $this->col($normalized, 'penyesuaian_adjustment', 'penyesuaianadjustment', 'penyesuaian')
        ) ?? 0;

        $saldoAkhir = IndonesianNumberParser::parse(
            $this->col($normalized, 'saldo_akhir', 'saldoakhir')
        ) ?? 0;

        $hasilPencacahan = $this->col($normalized, 'hasil_pencacahan', 'hasilmencacah', 'stock_opname');

        $jumlahSelisih = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_selisih', 'jumlahselisih', 'selisih')
        ) ?? 0;

        $line = new MutasiLine([
            'report_period_id'          => $this->reportPeriod->id,
            'kode_barang'               => $this->col($normalized, 'kode_barang', 'kodebarang'),
            'nama_barang'               => $this->col($normalized, 'nama_barang', 'namabarang'),
            'satuan_barang'             => $this->col($normalized, 'satuan_barang', 'satuanbarang', 'satuan'),
            'jumlah_barang'             => $jumlahBarang,
            'saldo_awal'                => $saldoAwal,
            'jumlah_pemasukan_barang'   => $jumlahPemasukan,
            'jumlah_pengeluaran_barang' => $jumlahPengeluaran,
            'penyesuaian_adjustment'    => $penyesuaian,
            'saldo_akhir'               => $saldoAkhir,
            'hasil_pencacahan'          => $hasilPencacahan ?? 'Belum',
            'jumlah_selisih'            => $jumlahSelisih,
            'keterangan'                => $this->col($normalized, 'keterangan', 'keterangan'),
        ]);

        $this->successCount++;
        return $line;
    }

    /**
     * Normalisasi row key: lowercase, hapus semua spasi & underscore.
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
     * Resolver kolom fleksibel — mencocokkan beberapa variasi header.
     */
    private function col(array $row, string ...$aliases): ?string
    {
        foreach ($aliases as $alias) {
            $normAlias = preg_replace('/[^a-z0-9]+/', '', strtolower($alias));
            if (isset($row[$normAlias]) && $row[$normAlias] !== null && $row[$normAlias] !== '') {
                return (string) $row[$normAlias];
            }
        }
        return null;
    }

    public function getRowErrors(): array
    {
        return $this->rowErrors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrors(): array
    {
        return $this->rowErrors;
    }
}