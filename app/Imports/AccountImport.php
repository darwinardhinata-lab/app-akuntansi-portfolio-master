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
            // MAPPING: Konversi nilai report_pos bahasa Inggris ke bahasa Indonesia
            // Database enum: (NERACA, LABA RUGI)
            // Template Inggris menggunakan: BALANCE SHEET, PROFIT & LOSS
            // =================================================================
            $reportPosMap = [
                'BALANCE SHEET' => 'NERACA',
                'PROFIT & LOSS' => 'LABA RUGI',
                'PROFIT AND LOSS' => 'LABA RUGI',
                'INCOME STATEMENT' => 'LABA RUGI',
            ];
            $posLaporanNormalized = $reportPosMap[strtoupper(trim($posLaporan))] ?? $posLaporan;

            // =================================================================
            // MAPPING: Konversi nilai coa_type yang melebihi varchar(50)
            // Database: coa_type = string(50)
            // Beberapa nilai template melebihi 50 karakter
            // =================================================================
            $coaTypeMap = [
                'ACCUMULATED DEPRECIATION OF FIXED ASSETS (NON-CURRENT ASSETS)' => 'Accum. Depreciation of Fixed Assets',
                'ACCOUNTS RECEIVABLE (CURRENT ASSETS)' => 'Accounts Receivable',
                'INVENTORY (CURRENT ASSETS)' => 'Inventory',
                'FIXED ASSETS (NON-CURRENT ASSETS)' => 'Fixed Assets',
                'OTHER ASSETS (NON-CURRENT ASSETS)' => 'Other Assets',
                'INTANGIBLE ASSETS (NON-CURRENT ASSETS)' => 'Intangible Assets',
                'DEFERRED TAX ASSETS (NON-CURRENT ASSETS)' => 'Deferred Tax Assets',
                'ACCRUED EXPENSES (CURRENT LIABILITIES)' => 'Accrued Expenses',
                'TAX PAYABLES (CURRENT LIABILITIES)' => 'Tax Payables',
                'DEFERRED REVENUE (CUSTOMER ADVANCES)' => 'Deferred Revenue',
                'DEPRECIATION & AMORTIZATION EXPENSES' => 'Depreciation & Amortization',
                'GENERAL & ADMINISTRATIVE EXPENSES' => 'General & Admin Expenses',
                'PREPAID TAXES (CURRENT ASSETS)' => 'Prepaid Taxes',
                'SECURITY DEPOSITS (CURRENT ASSETS)' => 'Security Deposits',
            ];
            $tipeNormalized = $coaTypeMap[strtoupper(trim($tipe))] ?? $tipe;
            // Safety: truncate to 50 chars if still too long
            if (strlen($tipeNormalized) > 50) {
                $tipeNormalized = substr($tipeNormalized, 0, 50);
            }

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
                    'coa_type'       => empty($tipeNormalized) ? 'Lainnya' : $tipeNormalized,
                    'normal_balance' => in_array($posSaldo, ['DEBET', 'KREDIT']) ? $posSaldo : 'DEBET',
                    'report_pos'     => in_array($posLaporanNormalized, ['NERACA', 'LABA RUGI']) ? $posLaporanNormalized : 'NERACA',
                ]
            );
        }
    }
}
