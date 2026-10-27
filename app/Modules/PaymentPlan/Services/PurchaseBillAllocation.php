<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Models\JournalHeader;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Support\JournalBalanceValidator;
use Illuminate\Support\Facades\DB;

final class PurchaseBillAllocation
{
    public function source(int $billId, int $company): array
    {
        if (DB::transactionLevel() === 0) throw new RuleViolation('ap_transaction_required', 409);
        // Shared legacy AP is safe only in the explicitly supported sole-MGI context.
        if (\App\Modules\Platform\Models\Company::count() !== 1 || !\App\Modules\Platform\Models\Company::whereKey($company)->where('code', 'MGI')->where('active', true)->exists()) throw new RuleViolation('ap_company_binding_required', 409);
        $bill = PurchaseBill::whereKey($billId)->lockForUpdate()->first();
        if (!$bill || !in_array($bill->payment_status, ['UNPAID', 'PARTIAL'], true)) throw new RuleViolation('open_purchase_bill_required', 409);
        $journal = $bill->journal_id ? JournalHeader::whereKey($bill->journal_id)->lockForUpdate()->first() : null;
        if (!$journal) throw new RuleViolation('posted_purchase_bill_required', 409);
        $lines = $journal->details()->get();
        if (!JournalBalanceValidator::isBalanced($lines->toArray())) throw new RuleViolation('invalid_purchase_bill_journal', 409);
        $codes = $lines->where('position', 'KREDIT')->pluck('account_code')->unique()->values();
        // Complex AP recognition requires explicit mapping/reconciliation, never guess counterpart.
        if ($codes->count() !== 1) throw new RuleViolation('purchase_bill_ap_mapping_required', 409);
        $code = $codes->first();
        if ($code !== $bill->credit_account || !DB::table('accounts')->where('account_code', $code)->where('normal_balance', 'KREDIT')->exists()) throw new RuleViolation('purchase_bill_ap_mapping_required', 409);
        $net = '0.00';
        foreach ($lines->where('account_code', $code) as $line) $net = $line->position === 'KREDIT' ? bcadd($net, (string) $line->amount, 2) : bcsub($net, (string) $line->amount, 2);
        if (bccomp($net, (string) $bill->grand_total, 2) !== 0 || bccomp($net, '0', 2) <= 0) throw new RuleViolation('purchase_bill_control_total_mismatch', 409);
        $allocated = '0.00';
        foreach (DB::table('ppl_ap_allocations')->where('purchase_bill_id', $billId)->whereIn('status', ['active', 'consumed'])->get() as $row) $allocated = bcadd($allocated, (string) $row->amount, 2);
        return ['id' => $billId, 'number' => $bill->bill_number, 'payee' => $bill->contact_name ?: $bill->vendor_name,
            'total' => $net, 'residual' => bcsub($net, $allocated, 2), 'account_code' => $code, 'journal_id' => $journal->getKey()];
    }

    public function reserve(array $request, int $company): void
    {
        if (DB::transactionLevel() === 0) throw new RuleViolation('ap_transaction_required', 409);
        $source = $this->source((int) ($request['purchaseBillId'] ?? 0), $company);
        $amount = (string) $request['settlement']['gross'];
        if (bccomp($amount, $source['residual'], 2) > 0) throw new RuleViolation('overpayment');
        DB::table('ppl_ap_allocations')->updateOrInsert(['request_id' => $request['id']], ['company_id' => $company,
            'purchase_bill_id' => $source['id'], 'amount' => $amount, 'status' => 'active',
            'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
}