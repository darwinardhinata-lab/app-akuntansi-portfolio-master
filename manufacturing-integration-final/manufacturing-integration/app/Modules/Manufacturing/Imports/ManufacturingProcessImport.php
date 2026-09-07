<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\ManufacturingProcess;
use App\Support\NumberParser;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Rate Proses. Kolom: NAMA PROSES, TIPE, SATUAN RATE, RATE.
 * Tidak pakai updateOrCreate (tabel ini tidak punya unique key alami seperti
 * kode) — selalu insert baru. Untuk update rate lama, edit manual lewat UI.
 */
class ManufacturingProcessImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    private array $validTypes = ['KNITTING', 'DYEING', 'PRINTING', 'FINISHING', 'CUTTING', 'STITCHING', 'OTHER'];

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
            if (strtoupper(trim((string) $row[0])) === 'NAMA PROSES') {
                continue;
            }

            $type = strtoupper(trim($row[1] ?? 'OTHER'));
            if (!in_array($type, $this->validTypes)) $type = 'OTHER';
            $rateUnit = strtoupper(trim($row[2] ?? 'PER_KG'));
            if (!in_array($rateUnit, ['PER_KG', 'PER_PCS'])) $rateUnit = 'PER_KG';

            ManufacturingProcess::create([
                'process_name' => trim($row[0]),
                'process_type' => $type,
                'rate_unit'    => $rateUnit,
                'process_rate' => NumberParser::parseDecimal($row[3] ?? '0'),
                'is_active'    => true,
            ]);
        }
    }
}
