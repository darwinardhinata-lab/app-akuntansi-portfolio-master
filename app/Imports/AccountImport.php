<?php

namespace App\Imports;

use App\Models\Account;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AccountImport implements ToCollection, WithStartRow
{
    /**
     * Memulai pembacaan dari baris ke-6 (Sesuai kaidah template COA Anda)
     */
    public function startRow(): int
    {
        return 6;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $kode = trim($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $tipe = trim($row[3] ?? '');
            $posSaldo   = strtoupper(trim($row[4] ?? ''));
            $posLaporan = strtoupper(trim($row[5] ?? ''));

            if (empty($kode) || empty($nama)) {
                continue;
            }

            // Normalisasi ejaan
            if (str_contains($posSaldo, 'DEB')) $posSaldo = 'DEBET';
            if (str_contains($posSaldo, 'KRE')) $posSaldo = 'KREDIT';

            // =================================================================
            // PERBAIKAN: Akun dengan prefix 8 adalah PENDAPATAN LAIN (KREDIT)
            // Sesuai RULES.md: 8 = Pendapatan Lain (Saldo Normal: KREDIT)
            // Koreksi otomatis prefix 8 DIHAPUS untuk mengizinkan data import tetap
            // =================================================================
            // Jika perlu koreksi khusus untuk akun tertentu, tambahkan di sini:
            // contoh: if ($kode == '88004') { $tipe = 'Biaya'; $posSaldo = 'KREDIT'; }

            Account::updateOrCreate(
                ['account_code' => $kode],
                [
                    'account_name'   => $nama,
                    'coa_type'       => empty($tipe) ? 'Lainnya' : $tipe,
                    'normal_balance' => in_array($posSaldo, ['DEBET', 'KREDIT']) ? $posSaldo : 'DEBET',
                    'report_pos'     => empty($posLaporan) ? 'NERACA' : $posLaporan,
                ]
            );
        }
    }
}
