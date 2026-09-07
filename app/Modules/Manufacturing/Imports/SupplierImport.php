<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Supplier;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import master Supplier/Vendor. Kolom: KODE, NAMA, TIPE, KODE BANTU,
 * KONTAK, TELEPON, EMAIL, ALAMAT, NPWP, TERMIN.
 */
class SupplierImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    private array $validTypes = ['RAW_MATERIAL', 'KNITTER', 'PROCESSOR', 'CUTTING', 'STITCHER', 'FINISHING', 'OTHER'];

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
            if (strtoupper(trim((string) $row[0])) === 'KODE SUPPLIER') {
                continue;
            }

            $type = strtoupper(trim($row[2] ?? 'OTHER'));
            if (!in_array($type, $this->validTypes)) $type = 'OTHER';

            Supplier::updateOrCreate(
                ['supplier_code' => trim($row[0])],
                [
                    'supplier_name'  => trim($row[1] ?? trim($row[0])),
                    'supplier_type'  => $type,
                    'helper_code'    => trim($row[3] ?? '') ?: null,
                    'contact_person' => trim($row[4] ?? '') ?: null,
                    'phone'          => trim($row[5] ?? '') ?: null,
                    'email'          => trim($row[6] ?? '') ?: null,
                    'address'        => trim($row[7] ?? '') ?: null,
                    'npwp'           => trim($row[8] ?? '') ?: null,
                    'payment_terms'  => trim($row[9] ?? '') ?: null,
                    'is_active'      => true,
                ]
            );
        }
    }
}
