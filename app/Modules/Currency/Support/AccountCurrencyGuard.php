<?php

namespace App\Modules\Currency\Support;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

class AccountCurrencyGuard
{
    /** Validate on the writer's connection, while its transaction holds the account locks. */
    public static function validate(array $rows, ConnectionInterface $connection): void
    {
        if ($rows === []) {
            return;
        }
        $codes = [];
        foreach ($rows as $row) {
            foreach (['account_code', 'currency_code', 'amount_txn'] as $column) {
                if (isset($row[$column]) && ! is_scalar($row[$column])) {
                    throw new RuntimeException('Konsistensi mata uang akun memerlukan nilai eksplisit: '.$column.'.');
                }
            }
            if (! isset($row['account_code']) || (string) $row['account_code'] === '') {
                throw new RuntimeException('Kode akun wajib diisi untuk pemeriksaan mata uang.');
            }
            $codes[] = (string) $row['account_code'];
        }
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);
        $accounts = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            foreach ($connection->table('accounts')->whereIn('account_code', $chunk)
                ->orderBy('account_code')->sharedLock()->get(['account_code', 'coa_type', 'currency_code']) as $account) {
                $accounts[$account->account_code] = $account;
            }
        }
        $base = null;
        foreach ($rows as $row) {
            $code = (string) $row['account_code'];
            $account = $accounts[$code] ?? null;
            if ($account === null) {
                throw new RuntimeException('Akun '.$code.' tidak ditemukan untuk pemeriksaan mata uang.');
            }
            // Omitted columns use the actual journal_details schema defaults, not company base.
            $currency = array_key_exists('currency_code', $row) ? $row['currency_code'] : 'IDR';
            $txn = $row['amount_txn'] ?? null;
            if ($account->currency_code !== null && $account->currency_code !== '') {
                if ($currency !== $account->currency_code || $txn === null || $txn === '') {
                    throw new RuntimeException('Akun '.$code.' hanya menerima baris '.$account->currency_code.' dengan nominal transaksi terisi.');
                }
            } elseif ($account->coa_type === 'Cash & Bank') {
                $base ??= BaseCurrency::code();
                if ($currency !== $base || $txn !== null) {
                    throw new RuntimeException('Akun '.$code.' merupakan kas/bank mata uang dasar '.$base.' dan tidak dapat menerima baris valas.');
                }
            }
        }
    }
}