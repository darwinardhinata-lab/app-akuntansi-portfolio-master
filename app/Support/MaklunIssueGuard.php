<?php

namespace App\Support;

use RuntimeException;

class MaklunIssueGuard
{
    public static function enabled(): void
    {
        if (! config('platform.maklun_issue_enabled', false)) {
            throw new RuntimeException('Issue maklun nonaktif sampai lifecycle receipt/reversal siap.');
        }
    }

    public static function source(string $status, mixed $qty, ?string $account, bool $yarn = false): string
    {
        if (! in_array($status, ['OPEN', 'ISSUED'], true)
            || ! is_numeric($qty) || ! is_finite((float) $qty) || (float) $qty <= 0
            || ($yarn && $account !== '114003')) {
            throw new RuntimeException('Status, kuantitas, atau akun asal issue maklun tidak valid.');
        }

        return FabricInventoryAccount::resolve($account);
    }
}
