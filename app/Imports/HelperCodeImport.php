<?php

namespace App\Imports;

use App\Models\HelperCode;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

// Tambahkan WithCustomCsvSettings agar bisa membaca titik koma (;)
class HelperCodeImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    /**
     * Memaksa sistem membaca pemisah kolom menggunakan titik koma (;)
     * karena template CSV Anda menggunakan format tersebut.
     */
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ';'
        ];
    }

    /**
     * Mulai membaca dari baris ke-7 (setelah baris judul/keterangan template)
     */
    public function startRow(): int
    {
        return 7; 
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Karena CSV sudah dipecah dengan titik koma, Array dimulai dari 0.
            // $row[0] = Kolom KODE BANTU
            // $row[1] = Kolom NAMA ENTITAS
            // $row[2] = Kolom MARKETING
            // $row[3] = Kolom POS SALDO NORMAL
            
            // Jika kolom KODE BANTU kosong, lewati baris ini
            if (!isset($row[0]) || empty(trim($row[0]))) {
                continue;
            }

            // Proteksi tambahan jika tidak sengaja membaca baris judul
            if (strtoupper(trim($row[0])) === 'KODE BANTU') {
                continue;
            }

            HelperCode::updateOrCreate(
                [
                    'helper_code' => trim($row[0]) // Cari berdasarkan Kode Bantu
                ],
                [
                    'entity_name'    => trim($row[1] ?? '-'), 
                    'marketing_name' => trim($row[2] ?? '-'), 
                    'normal_balance' => strtoupper(trim($row[3] ?? 'DEBET')), 
                ]
            );
        }
    }
}