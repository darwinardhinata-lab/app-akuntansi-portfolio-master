<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use Illuminate\Support\Facades\DB;

final class LegacyFinancialFence
{
    /** Acquire before invoice/PO/journal locks, matching the enabled bank workflow. */
    public static function lock(bool $required = false): void
    {
        if (!$required && !config('payment_plan_v33.bank_workflow', false)) return;
        if (DB::transactionLevel() === 0) throw new RuleViolation('financial_transaction_required', 409);
        $contexts = DB::table('ppl_financial_contexts')->orderBy('company_id')->lockForUpdate()->get();
        if ($required && $contexts->isEmpty()) throw new RuleViolation('financial_context_required', 409);
    }
}