<?php

namespace App\Imports;

use App\Jobs\TranslateAccountNamesJob;
use App\Models\Account;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
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
        // Validate the whole collection before any account is written.
        $balanceMap = [
            'DEBET' => 'DEBET', 'DEBIT' => 'DEBET', '借方' => 'DEBET',
            'KREDIT' => 'KREDIT', 'CREDIT' => 'KREDIT', '贷方' => 'KREDIT',
        ];
        $reportLabels = ['NERACA', 'LABA RUGI', 'BALANCE SHEET', 'PROFIT & LOSS',
            'PROFIT AND LOSS', 'INCOME STATEMENT', '资产负债表', '损益表', '损益'];
        foreach ($rows->values() as $index => $row) {
            if (trim($row[1] ?? '') === '' || trim($row[2] ?? '') === '') {
                continue;
            }
            if (!isset($balanceMap[strtoupper(trim($row[4] ?? ''))])
                || !in_array(strtoupper(trim($row[5] ?? '')), $reportLabels, true)) {
                throw ValidationException::withMessages([
                    'file' => __('erp.audit_coa_import_invalid', ['row' => $index + $this->startRow()]),
                ]);
            }
        }
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
            $posSaldo = $balanceMap[$posSaldo];

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

            Account::updateOrCreate(
                ['account_code' => $kode],
                [
                    'account_name'   => $nama,
                    'coa_type'       => empty($tipeNormalized) ? 'Lainnya' : $tipeNormalized,
                    'normal_balance' => $posSaldo,
                    'report_pos'     => $posLaporanNormalized,
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
