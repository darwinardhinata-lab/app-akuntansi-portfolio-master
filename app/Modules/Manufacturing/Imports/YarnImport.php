<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Yarn. Kolom: KODE YARN, JENIS, COUNT, KOMPOSISI, WARNA, SATUAN.
 * Mengikuti pola HelperCodeImport (updateOrCreate by kode, mulai baca baris 7
 * mengikuti template yang di-download via downloadTemplate()).
 * CATATAN: stock_quantity & average_cost SENGAJA tidak diimport lewat sini —
 * itu HANYA boleh berubah lewat transaksi (MRN/issue), bukan import massal,
 * demi menjaga integritas kartu stok & HPP moving average.
 */
class YarnImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    public function getCsvSettings(): array
    {
        return ['delimiter' => ';'];
    }

    public function startRow(): int
    {
        return 7;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (!isset($row[0]) || empty(trim((string) $row[0]))) {
                continue;
            }
            if (strtoupper(trim((string) $row[0])) === 'KODE YARN') {
                continue;
            }

            Yarn::updateOrCreate(
                ['yarn_code' => trim($row[0])],
                [
                    'yarn_type'   => trim($row[1] ?? '-'),
                    'yarn_count'  => trim($row[2] ?? '') ?: null,
                    'composition' => trim($row[3] ?? '') ?: null,
                    'color'       => trim($row[4] ?? '') ?: null,
                    'unit'        => trim($row[5] ?? 'KGS'),
                    'inventory_account_code' => config('coa.persediaan_bahan_baku_benang'),
                    'is_active'   => true,
                ]
            );
        }
    }
}
