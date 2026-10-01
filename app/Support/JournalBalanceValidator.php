<?php

namespace App\Support;

class JournalBalanceValidator
{
    /**
     * Memvalidasi keseimbangan array detail jurnal (Debet vs Kredit).
     * Mencegah jurnal unbalanced masuk ke database.
     */
    public static function isBalanced(array $details): bool
    {
        $totalDebet = '0.00';
        $totalKredit = '0.00';

        foreach ($details as $detail) {
            $amount = JournalAmount::normalize($detail['amount'] ?? 0);
            if (bccomp($amount, '0.00', 2) !== 0) {
                if (strtoupper($detail['position']) === 'DEBET') {
                    $totalDebet = bcadd($totalDebet, $amount, 2);
                } else {
                    $totalKredit = bcadd($totalKredit, $amount, 2);
                }
            }
        }

        return bccomp($totalDebet, $totalKredit, 2) === 0;
    }

    /**
     * Helper untuk menghitung nilai selisih (jika dibutuhkan untuk pesan error)
     */
    public static function getDifference(array $details): string
    {
        $totalDebet = '0.00';
        $totalKredit = '0.00';

        foreach ($details as $detail) {
            $amount = JournalAmount::normalize($detail['amount'] ?? 0);
            if (strtoupper($detail['position']) === 'DEBET') {
                $totalDebet = bcadd($totalDebet, $amount, 2);
            } else {
                $totalKredit = bcadd($totalKredit, $amount, 2);
            }
        }

        return bccomp($totalDebet, $totalKredit, 2) >= 0
            ? bcsub($totalDebet, $totalKredit, 2)
            : bcsub($totalKredit, $totalDebet, 2);
    }
}