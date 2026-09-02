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
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($details as $detail) {
            $amount = (float) ($detail['amount'] ?? 0);
            if ($amount != 0) {
                if (strtoupper($detail['position']) === 'DEBET') {
                    $totalDebet += $amount;
                } else {
                    $totalKredit += $amount;
                }
            }
        }

        return bccomp((string) round($totalDebet, 2), (string) round($totalKredit, 2), 2) === 0;
    }

    /**
     * Helper untuk menghitung nilai selisih (jika dibutuhkan untuk pesan error)
     */
    public static function getDifference(array $details): float
    {
        $totalDebet = 0;
        $totalKredit = 0;

        foreach ($details as $detail) {
            $amount = (float) ($detail['amount'] ?? 0);
            if (strtoupper($detail['position']) === 'DEBET') {
                $totalDebet += $amount;
            } else {
                $totalKredit += $amount;
            }
        }

        return abs(round($totalDebet, 2) - round($totalKredit, 2));
    }
}