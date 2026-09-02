<?php

namespace App\Support;

class AccountClassifier
{
    /**
     * Menentukan kelompok laporan dan arah saldo (Kredit/Debet)
     * secara dinamis berdasarkan prefix dan saldo normal dari Master COA.
     */
    public static function determineGroup(string $prefix, ?string $normalBalance): array
    {
        $isKredit = strtoupper(trim($normalBalance ?? '')) === 'KREDIT';

        switch ($prefix) {
            case '4':
                // PERBAIKAN: Gunakan normal_balance dari Master COA agar akun kontra
                // (misal: Diskon Penjualan, Retur Penjualan) yang memiliki normal_balance = 'DEBET'
                // dapat diidentifikasi dengan benar sebagai isKredit = false.
                return ['group' => 'pendapatan', 'isKredit' => $isKredit];
            case '5':
                return ['group' => 'hpp', 'isKredit' => false];
            case '6':
                return ['group' => 'biaya', 'isKredit' => false];
            case '7':
                return ['group' => $isKredit ? 'pendapatan_lain' : 'biaya', 'isKredit' => $isKredit];
            case '8':
            case '9':
                return ['group' => $isKredit ? 'pendapatan_lain' : 'beban_lain', 'isKredit' => $isKredit];
            default:
                return ['group' => 'beban_lain', 'isKredit' => false];
        }
    }
}