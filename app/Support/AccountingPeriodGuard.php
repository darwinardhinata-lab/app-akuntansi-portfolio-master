<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AccountingPeriodGuard
{
    public static function enabled(): bool
    {
        return config('platform.period_lifecycle_preview_enabled', false) && app()->environment('testing');
    }

    /** Coarse shared mutex: close and protected writes must hold it until commit. */
    public static function lock(): void
    {
        DB::table('accounting_periods')->insertOrIgnore(['month' => 'GLOBAL', 'closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounting_periods')->where('month', 'GLOBAL')->lockForUpdate()->first();
    }

    public static function dates(array $dates): void
    {
        foreach ($dates as $date) {
            if (!is_string($date) || !preg_match('/\A\d{4}-(0[1-9]|1[0-2])-\d{2}(?:[ T].*)?\z/', $date)) {
                throw new \RuntimeException('Explicit valid transaction date is required for period protection.');
            }
            $month = substr($date, 0, 7);
            // Current locking read: a prior consistent-read snapshot may predate a concurrent close.
            $period = DB::table('accounting_periods')->where('month', $month)->lockForUpdate()->first();
            if ($period && $period->closed) {
                throw new \RuntimeException('Accounting period is closed: '.$month);
            }
        }
    }

    public static function journals(array $ids): void
    {
        $dates = ProtectedJournalQuery::table('journal_headers')->whereIn('journal_id', array_unique($ids))->pluck('transaction_date')->all();
        self::dates($dates);
    }

    public static function source(array $dates): void
    {
        if (!self::enabled()) return;
        if (DB::transactionLevel() === 0) throw new \RuntimeException('Period-protected source mutation requires an outer transaction.');
        self::lock();
        self::dates($dates);
    }
}