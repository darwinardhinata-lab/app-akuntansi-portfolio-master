<?php

namespace App\Modules\CustomsReports\Imports;

use App\Modules\CustomsReports\Models\PosisiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Support\IndonesianNumberParser;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Import laporan WIP / Posisi Barang Dalam Proses (#4).
 *
 * Format file sesuai 101109.xls.
 */
class PosisiImport extends StringValueBinder implements ToModel, WithHeadingRow, WithCustomCsvSettings, WithCustomValueBinder
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

    public function model(array $row): ?PosisiLine
    {
        // Guard: tolak import jika periode sudah FINAL / DIUNGGAH
        if ($this->reportPeriod->isFinal() || $this->reportPeriod->isUploaded()) {
            throw new \RuntimeException(
                'Periode laporan sudah FINAL/DIUNGGAH. Import tidak diperbolehkan.'
            );
        }

        $normalized = $this->normalizeRow($row);

        $jumlahBarang = IndonesianNumberParser::parse(
            $this->col($normalized, 'jumlah_barang', 'jumlahbarang', 'jumlah')
        );

        // 0,00 adalah nilai SAH — hanya null (kosong/tak terbaca) yang dilewati.
        if ($jumlahBarang === null) {
            $this->errors[] = "Baris {$this->nextRowNumber()}: jumlah_barang tidak valid, dilewati.";
            return null;
        }

        $this->successCount++;

        return new PosisiLine([
            'report_period_id' => $this->reportPeriod->id,
            'kode_barang'      => $this->col($normalized, 'kode_barang', 'kodebarang'),
            'nama_barang'      => $this->col($normalized, 'nama_barang', 'namabarang'),
            'satuan_barang'    => $this->col($normalized, 'satuan_barang', 'satuanbarang', 'satuan'),
            'jumlah_barang'    => $jumlahBarang,
            'keterangan'       => $this->col($normalized, 'keterangan') ?: null,
        ]);
    }

    /**
     * Normalisasi row key: lowercase, hapus semua karakter non-alfanumerik.
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
     * (PHP tidak mengizinkan aritmatika di dalam interpolasi string.)
     */
    private function nextRowNumber(): int
    {
        return $this->successCount + count($this->errors) + 1;
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

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}