<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Fabric;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Fabric. Kolom: KODE FABRIC, JENIS, SUBTYPE, STATE (GREY/FINISHED),
 * GSM, KOMPOSISI, LEBAR, WARNA, SATUAN.
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
            if (strtoupper(trim((string) $row[0])) === 'KODE FABRIC') {
                continue;
            }

            $state = strtoupper(trim($row[3] ?? 'FINISHED'));
            if (!in_array($state, ['GREY', 'FINISHED'])) $state = 'FINISHED';

            Fabric::updateOrCreate(
                ['fabric_code' => trim($row[0])],
                [
                    'fabric_type'  => trim($row[1] ?? '-'),
                    'subtype'      => trim($row[2] ?? '') ?: null,
                    'state'        => $state,
                    'gsm'          => is_numeric($row[4] ?? null) ? (int) $row[4] : null,
                    'composition'  => trim($row[5] ?? '') ?: null,
                    'width'        => is_numeric($row[6] ?? null) ? (float) $row[6] : null,
                    'color'        => trim($row[7] ?? '') ?: null,
                    'unit'         => trim($row[8] ?? 'KGS'),
                    'inventory_account_code' => config('coa.persediaan_bahan_baku_kain'),
                    'is_active'    => true,
                ]
            );
        }
    }
}
