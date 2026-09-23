<?php

namespace App\Imports;

use App\Jobs\TranslateAccountNamesJob;
use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
        // FIX: kumpulkan account_code & coa_type yang kena sentuh di batch ini,
        // supaya bisa dispatch 1 job translate setelah loop selesai (bukan per baris)
        $touchedAccountCodes = [];
        $touchedCoaTypes = [];

        foreach ($rows as $row) {
            $kode = trim($row[1] ?? '');
            $nama = trim($row[2] ?? '');
            $tipe = trim($row[3] ?? '');
            $posSaldo   = strtoupper(trim($row[4] ?? ''));
            $posLaporan = strtoupper(trim($row[5] ?? ''));

            if (empty($kode) || empty($nama)) {
                continue;
            }

            // FIX: normalisasi ejaan -- tambahkan pengenalan kata kunci Bahasa Mandarin
            // (借/贷) di samping Inggris (DEB/KRE). Sebelumnya, import dari template
            // Chinese Simplified (Pos Saldo berisi 借方/贷方) tidak match string apa pun
            // di sini, sehingga JATUH DIAM-DIAM ke fallback default 'DEBET' di bawah --
            // berisiko akun kredit tersimpan salah jadi debit tanpa error apa pun.
            $posSaldoValid = true;
            if (str_contains($posSaldo, 'DEB') || str_contains($posSaldo, '借')) {
                $posSaldo = 'DEBET';
            } elseif (str_contains($posSaldo, 'KRE') || str_contains($posSaldo, '贷')) {
                $posSaldo = 'KREDIT';
            } else {
                $posSaldoValid = false;
            }

            // =================================================================
            // MAPPING: Konversi nilai report_pos ke bahasa Indonesia
            // Database enum: (NERACA, LABA RUGI)
            // Template Inggris: BALANCE SHEET, PROFIT & LOSS / INCOME STATEMENT
            // Template Mandarin: 资产负债表 (Neraca), 损益表 / 损益 (Laba Rugi)
            // FIX: tambahkan entri Mandarin -- sebelumnya hanya bahasa Inggris,
            // sama seperti Pos Saldo di atas, ini juga jatuh diam-diam ke 'NERACA'.
            // =================================================================
            $reportPosMap = [
                'BALANCE SHEET' => 'NERACA',
                'PROFIT & LOSS' => 'LABA RUGI',
                'PROFIT AND LOSS' => 'LABA RUGI',
                'INCOME STATEMENT' => 'LABA RUGI',
                '资产负债表' => 'NERACA',
                '损益表' => 'LABA RUGI',
                '损益' => 'LABA RUGI',
            ];
            $posLaporanKey = strtoupper(trim($posLaporan));
            $posLaporanValid = isset($reportPosMap[$posLaporanKey]) || in_array($posLaporan, ['NERACA', 'LABA RUGI'], true);
            $posLaporanNormalized = $reportPosMap[$posLaporanKey] ?? $posLaporan;

            // =================================================================
            // MAPPING: Konversi nilai coa_type yang melebihi varchar(50)
            // Database: coa_type = string(50)
            // Beberapa nilai template melebihi 50 karakter
            //
            // CATATAN PENTING (belum diperbaiki di patch ini, butuh keputusan
            // terpisah -- lihat audit coa:audit-type-mismatch):
            // Map ini hanya menangkap label panjang bahasa Inggris dan tidak
            // pernah menghasilkan salah satu dari 15 kategori resmi yang dipakai
            // <select name="coa_type"> di form Tambah/Edit Akun (Cash & Bank,
            // Piutang Dagang, Aset Tetap, dst). Import dari template ID/EN/ZH
            // manapun berpotensi menghasilkan coa_type yang TIDAK match dropdown
            // itu. Ini pre-existing, bukan regresi dari patch ini -- sengaja
            // TIDAK diubah sampai ada keputusan pemetaan 27 sub-kategori
            // template -> 15 kategori resmi dari Anda.
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

            // FIX: log peringatan (bukan silent fail) kalau Pos Saldo/Pos Laporan
            // tidak dikenali sama sekali, supaya ketahuan di log bukan cuma
            // tersembunyi sebagai default yang salah.
            if (!$posSaldoValid || !$posLaporanValid) {
                Log::warning('AccountImport: Pos Saldo/Pos Laporan tidak dikenali, dipakai nilai default.', [
                    'account_code'      => $kode,
                    'pos_saldo_raw'     => $row[4] ?? null,
                    'pos_laporan_raw'   => $row[5] ?? null,
                ]);
            }

            Account::updateOrCreate(
                ['account_code' => $kode],
                [
                    'account_name'   => $nama,
                    'coa_type'       => empty($tipeNormalized) ? 'Lainnya' : $tipeNormalized,
                    'normal_balance' => in_array($posSaldo, ['DEBET', 'KREDIT']) ? $posSaldo : 'DEBET',
                    'report_pos'     => in_array($posLaporanNormalized, ['NERACA', 'LABA RUGI']) ? $posLaporanNormalized : 'NERACA',
                ]
            );

            $touchedAccountCodes[] = $kode;
            if (!empty($tipeNormalized)) {
                $touchedCoaTypes[] = $tipeNormalized;
            }
        }

        // FIX: dispatch translate job 1x untuk seluruh batch (bukan per baris),
        // supaya tidak menahan proses import menunggu panggilan API translasi.
        if (!empty($touchedAccountCodes)) {
            TranslateAccountNamesJob::dispatch($touchedAccountCodes, array_unique($touchedCoaTypes));
        }
    }
}
