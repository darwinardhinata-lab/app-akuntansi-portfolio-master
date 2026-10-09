<?php

namespace App\Services;

use App\Models\User;
use App\Support\JournalBalanceValidator;
use App\Support\ProtectedJournalQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function change(string $month, bool $close, string $reason, User $actor): void
    {
        // Lifecycle foundation only: keep inaccessible until mutation guards are completed.
        abort_unless(config('platform.period_lifecycle_preview_enabled', false) && app()->environment('testing'), 409,
            'Accounting period lifecycle is not operational until transaction guards are verified.');
        $action = $close ? 'close' : 'reopen';
        abort_unless($actor->role === 'FINANCE' && in_array((string) $actor->id,
            array_map('strval', config('platform.period_'.$action.'_user_ids', [])), true), 403);
        if (!preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $month) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['period' => 'Invalid month or review reason.']);
        }
        DB::transaction(function () use ($month, $close, $reason, $actor, $action) {
            \App\Support\AccountingPeriodGuard::lock();
            DB::table('accounting_periods')->insertOrIgnore(['month' => $month, 'closed' => false, 'created_at' => now(), 'updated_at' => now()]);
            $period = DB::table('accounting_periods')->where('month', $month)->lockForUpdate()->first();
            if ((bool) $period->closed === $close) return;
            if ($close) {
                $start = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month);
                foreach (ProtectedJournalQuery::table('journal_headers')->where('transaction_date', '>=', $start->toDateString())
                    ->where('transaction_date', '<', $start->copy()->addMonth()->toDateString())->lockForUpdate()->cursor() as $header) {
                    $lines = ProtectedJournalQuery::table('journal_details')->where('journal_id', $header->journal_id)->lockForUpdate()->get();
                    if ($lines->isEmpty() || !$lines->every(fn ($line) => in_array($line->position, ['DEBET', 'KREDIT'], true) && (float) $line->amount >= 0)
                        || !JournalBalanceValidator::isBalanced($lines->map(fn ($line) => (array) $line)->all())) {
                        throw ValidationException::withMessages(['period' => 'Unbalanced/invalid journal: '.$header->journal_id]);
                    }
                }
            }
            DB::table('accounting_periods')->where('month', $month)->update(['closed' => $close, 'updated_at' => now()]);
            DB::table('accounting_period_events')->insert(['month' => $month, 'action' => $action, 'user_id' => $actor->id,
                'reason' => trim($reason), 'created_at' => now(), 'updated_at' => now()]);
        });
    }
}