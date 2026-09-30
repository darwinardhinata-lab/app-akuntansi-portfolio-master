<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Fabric;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Fabric. Format baru: HS CODE, KODE BAHAN, DESCRIPTION, NAMA BAHAN,
 * NAMA MATERIAL INGGRIS, KATEGORI, WARNA, SPESIFIKASI/DESKRIPSI, SATUAN, METER PER GULUNG.
 * stock_quantity & average_cost TIDAK diimport lewat sini (sama seperti YarnImport).
 */
class FabricImport implements ToCollection, WithStartRow, WithCustomCsvSettings
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
            if (in_array(strtoupper(trim((string) $row[0])), ['KODE FABRIC', 'KODE BAHAN', 'HS CODE'], true)) {
                continue;
            }

            $isStandardFormat = count($row) >= 10;
            $code = trim((string) ($isStandardFormat ? ($row[1] ?? '') : ($row[0] ?? '')));
            if ($code === '') continue;
            $description = trim((string) ($isStandardFormat ? ($row[2] ?? '-') : ($row[1] ?? '-')));
            $state = strtoupper(trim((string) ($isStandardFormat ? 'FINISHED' : ($row[3] ?? 'FINISHED'))));
            if (!in_array($state, ['GREY', 'FINISHED'])) $state = 'FINISHED';

            Fabric::updateOrCreate(
                ['fabric_code' => $code],
                [
                    'fabric_type'  => $description,
                    'description' => $description,
                    'hs_code' => $isStandardFormat ? trim((string) ($row[0] ?? '')) ?: null : null,
                    'material_name' => $isStandardFormat ? trim((string) ($row[3] ?? '')) ?: null : null,
                    'english_name' => $isStandardFormat ? trim((string) ($row[4] ?? '')) ?: null : null,
                    'category' => $isStandardFormat ? trim((string) ($row[5] ?? '')) ?: null : null,
                    'specification' => $isStandardFormat ? trim((string) ($row[7] ?? '')) ?: null : null,
                    'meters_per_roll' => $isStandardFormat && is_numeric($row[9] ?? null) ? (float) $row[9] : null,
                    'subtype' => $isStandardFormat ? null : (trim((string) ($row[2] ?? '')) ?: null),
                    'state'        => $state,
                    'gsm' => $isStandardFormat ? null : (is_numeric($row[4] ?? null) ? (int) $row[4] : null),
                    'composition' => $isStandardFormat ? null : (trim((string) ($row[5] ?? '')) ?: null),
                    'width' => $isStandardFormat ? null : (is_numeric($row[6] ?? null) ? (float) $row[6] : null),
                    'color' => trim((string) ($isStandardFormat ? ($row[6] ?? '') : ($row[7] ?? ''))) ?: null,
                    'unit' => trim((string) ($isStandardFormat ? ($row[8] ?? 'KGS') : ($row[8] ?? 'KGS'))),
                    'inventory_account_code' => config('coa.persediaan_bahan_baku_kain'),
                    'is_active'    => true,
                ]
            );
        }
    }
}
