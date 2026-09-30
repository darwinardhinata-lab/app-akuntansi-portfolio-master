<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AuxiliaryMaterialImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    public function getCsvSettings(): array { return ['delimiter' => ';']; }
    public function startRow(): int { return 7; }
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $isStandardFormat = count($row) >= 10;
            $code = trim((string) ($isStandardFormat ? ($row[1] ?? '') : ($row[0] ?? '')));
            if ($code === '' || in_array(strtoupper($code), ['KODE MATERIAL', 'KODE BAHAN'], true)) continue;
            AuxiliaryMaterial::updateOrCreate(['material_code' => $code], [
                'hs_code' => $isStandardFormat ? trim((string) ($row[0] ?? '')) ?: null : null,
                'description' => $isStandardFormat ? trim((string) ($row[2] ?? '')) ?: null : null,
                'material_name' => trim((string) ($isStandardFormat ? ($row[3] ?? '-') : ($row[1] ?? '-'))),
                'english_name' => $isStandardFormat ? trim((string) ($row[4] ?? '')) ?: null : null,
                'category' => trim((string) ($isStandardFormat ? ($row[5] ?? '') : ($row[2] ?? ''))) ?: null,
                'color' => $isStandardFormat ? trim((string) ($row[6] ?? '')) ?: null : null,
                'specification' => trim((string) ($isStandardFormat ? ($row[7] ?? '') : ($row[3] ?? ''))) ?: null,
                'unit' => trim((string) ($isStandardFormat ? ($row[8] ?? 'PCS') : ($row[4] ?? 'PCS'))),
                'meters_per_roll' => $isStandardFormat && is_numeric($row[9] ?? null) ? (float) $row[9] : null,
                'inventory_account_code' => '114004', 'is_active' => true,
            ]);
        }
    }
}