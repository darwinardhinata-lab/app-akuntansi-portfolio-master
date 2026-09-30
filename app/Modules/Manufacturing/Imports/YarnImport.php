<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Yarn. Format baru: HS CODE, KODE BAHAN, DESCRIPTION, NAMA BAHAN,
 * NAMA MATERIAL INGGRIS, KATEGORI, WARNA, SPESIFIKASI/DESKRIPSI, SATUAN, METER PER GULUNG.
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
            if (in_array(strtoupper(trim((string) $row[0])), ['KODE YARN', 'KODE BAHAN', 'HS CODE'], true)) {
                continue;
            }

            $isStandardFormat = count($row) >= 10;
            $code = trim((string) ($isStandardFormat ? ($row[1] ?? '') : ($row[0] ?? '')));
            if ($code === '') continue;
            $description = trim((string) ($isStandardFormat ? ($row[2] ?? '-') : ($row[1] ?? '-')));

            Yarn::updateOrCreate(
                ['yarn_code' => $code],
                [
                    'yarn_type'   => $description,
                    'description' => $description,
                    'hs_code' => $isStandardFormat ? trim((string) ($row[0] ?? '')) ?: null : null,
                    'material_name' => $isStandardFormat ? trim((string) ($row[3] ?? '')) ?: null : null,
                    'english_name' => $isStandardFormat ? trim((string) ($row[4] ?? '')) ?: null : null,
                    'category' => $isStandardFormat ? trim((string) ($row[5] ?? '')) ?: null : null,
                    'color' => trim((string) ($isStandardFormat ? ($row[6] ?? '') : ($row[4] ?? ''))) ?: null,
                    'specification' => $isStandardFormat ? trim((string) ($row[7] ?? '')) ?: null : null,
                    'unit' => trim((string) ($isStandardFormat ? ($row[8] ?? 'KGS') : ($row[5] ?? 'KGS'))),
                    'meters_per_roll' => $isStandardFormat && is_numeric($row[9] ?? null) ? (float) $row[9] : null,
                    'yarn_count'  => $isStandardFormat ? null : (trim((string) ($row[2] ?? '')) ?: null),
                    'composition' => $isStandardFormat ? null : (trim((string) ($row[3] ?? '')) ?: null),
                    'inventory_account_code' => config('coa.persediaan_bahan_baku_benang'),
                    'is_active'   => true,
                ]
            );
        }
    }
}
