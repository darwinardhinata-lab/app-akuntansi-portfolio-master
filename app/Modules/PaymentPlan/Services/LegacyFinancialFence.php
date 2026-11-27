<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class LegacyFinancialFence
{
    public static function bankCapacityEnabled(): bool
    {
        return config('payment_plan_v33.bank_workflow', false) && config('payment_plan_v33.erp_bank_balance', false);
    }

    /** Call before writes, under the same financial fence as Maker. */
    public static function assertBankCreditDate(string $glDate, array $lines): void
    {
        if (self::futureBankCreditRows([['date' => $glDate, 'lines' => $lines]])) {
            throw new RuleViolation('future_protected_bank_credit', 409);
        }
    }

    /** Return source labels of final GL lines that lower a future protected balance. */
    public static function futureBankCreditRows(iterable $journals): array
    {
        if (!config('payment_plan_v33.bank_workflow', false)) return [];
        if (DB::transactionLevel() === 0) throw new RuleViolation('financial_transaction_required', 409);

        $today = ErpBankBalance::businessDate();
        $credits = [];
        foreach ($journals as $journal) {
            if (Carbon::parse($journal['date'])->toDateString() <= $today) continue;
            foreach ($journal['lines'] as $line) {
                $direction = bccomp((string) $line['amount'], '0.00', 2);
                if (($line['position'] === 'KREDIT' && $direction > 0)
                    || ($line['position'] === 'DEBET' && $direction < 0)) {
                    $credits[$line['account_code']][] = $line['source'] ?? true;
                }
            }
        }
        if (!$credits) return [];

        $keys = array_map(static fn ($id) => 'ppl.bank.'.$id, array_keys(config('payment_plan_v33.payment_accounts', [])));
        $matches = [];
        foreach (array_chunk(array_keys($credits), 500) as $codes) {
            $matched = DB::table('company_coa_mappings')->where('active', true)->whereIn('semantic_key', $keys)
                ->whereIn('account_code', $codes)->distinct()->pluck('account_code');
            foreach ($matched as $code) foreach ($credits[$code] as $source) $matches[] = $source;
        }
        return array_values(array_unique($matches, SORT_REGULAR));
    }

    /** Turn the removal of an existing, currently projected journal into opposite ledger lines. */
    public static function removedBankLines(\App\Models\JournalHeader $journal, string $today): array
    {
        if (substr((string) $journal->transaction_date, 0, 10) > $today) return [];

        return $journal->details()->get(['account_code', 'position', 'amount'])->map(static fn ($line) => [
            'account_code' => $line->account_code,
            'position' => match ($line->position) {
                'DEBET' => 'KREDIT',
                'KREDIT' => 'DEBET',
                default => throw new RuleViolation('invalid_bank_ledger', 409),
            },
            'amount' => (string) $line->amount,
        ])->all();
    }

    /** Manual GL debits must not spend capacity held by the bank workflow.
     * Call immediately after lock(), before any journal writes or snapshot reads.
     */
    public static function assertBankCapacity(array $lines): void
    {
        if (!self::bankCapacityEnabled()) return;
        if (DB::transactionLevel() === 0) throw new RuleViolation('financial_transaction_required', 409);
        $net = [];
        foreach ($lines as $line) {
            $code = $line['account_code'];
            $amount = (string) $line['amount'];
            $net[$code] = ($line['position'] === 'KREDIT')
                ? bcadd($net[$code] ?? '0.00', $amount, 2)
                : bcsub($net[$code] ?? '0.00', $amount, 2);
        }
        foreach (DB::table('ppl_financial_contexts')->orderBy('company_id')->lockForUpdate()->get() as $context) {
            $settings = json_decode($context->settings, true, 512, JSON_THROW_ON_ERROR);
            foreach ($settings['accounts'] ?? [] as $account) {
                if (($account['currency'] ?? null) !== 'IDR'
                    || !isset(config('payment_plan_v33.payment_accounts')[$account['id']])) continue;
                $code = DB::table('company_coa_mappings')->where('company_id', $context->company_id)
                    ->where('semantic_key', 'ppl.bank.'.$account['id'])->where('active', true)->value('account_code');
                if (!$code || bccomp($net[$code] ?? '0.00', '0.00', 2) <= 0) continue;
                $balance = (new ErpBankBalance())->project((int) $context->company_id, $account, $settings);
                if (bccomp($net[$code], $balance['available'], 2) > 0) {
                    throw new RuleViolation('funds_insufficient', 409);
                }
            }
        }
    }

    /** Acquire before invoice/PO/journal locks, matching the enabled bank workflow. */
    public static function lock(bool $required = false): void
    {
        if (!$required && !config('payment_plan_v33.bank_workflow', false)) return;
        if (DB::transactionLevel() === 0) throw new RuleViolation('financial_transaction_required', 409);
        $contexts = DB::table('ppl_financial_contexts')->orderBy('company_id')->lockForUpdate()->get();
        if ($required && $contexts->isEmpty()) throw new RuleViolation('financial_context_required', 409);
    }
}