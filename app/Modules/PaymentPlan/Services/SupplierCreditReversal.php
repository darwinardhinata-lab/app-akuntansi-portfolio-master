<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Modules\Platform\Support\CompanyContext;
use App\Modules\Platform\Support\OperationalCompany;
use App\Support\AccountingPeriodGuard;
use App\Support\PostingService;
use App\Support\ProtectedJournalQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** One immutable cancellation/audit row also carries the approved-attribution correction. */
final class SupplierCreditReversal
{
    public function reverse(Request $request, int $billId, int $bindingId): object
    {
        $data = $request->validate(['posting_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:255']);
        return DB::transaction(function () use ($request, $billId, $bindingId, $data) {
            abort_unless(config('platform.order_company_scope_enabled') && config('platform.posting_intents_enabled')
                && config('payment_plan_v33.bank_workflow'), 409);
            LegacyFinancialFence::lock(true);
            $company = app(OperationalCompany::class)->authorizeRequest($request);
            $actor = $request->user()->fresh();
            abort_unless($actor && $actor->role === 'FINANCE'
                && app(CompanyContext::class)->allows($actor, $company, 'supplier_credit.reverse'), 403);
            AccountingPeriodGuard::lock();
            $bill = PurchaseBill::whereKey($billId)->lockForUpdate()->firstOrFail();
            $party = SupplierCreditPosting::supplier($bill, $company->id);
            $binding = DB::table('supplier_credit_bindings')->where('id', $bindingId)->lockForUpdate()->first();
            abort_unless($binding && (int) $binding->company_id === (int) $company->id
                && (int) $binding->party_id === $party && (int) $binding->purchase_bill_id === $billId, 409, 'Supplier credit ownership mismatch.');
            $hash = self::payloadHash($bindingId, $data['posting_date'], $data['reason']);
            $existing = DB::table('supplier_credit_reversals')->where('binding_id', $bindingId)->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash) && self::valid($binding, $existing), 409, 'Supplier credit reversal conflict.');
                return $existing;
            }
            abort_unless(SupplierCreditPosting::sealed($binding), 409, 'Supplier credit source is unavailable.');
            $source = SupplierCreditPosting::eligible($bill, $company->id);
            abort_if($source['exceptions'], 409, 'Invoice provenance is unresolved.');
            try { AccountingPeriodGuard::dates([$data['posting_date']]); }
            catch (\RuntimeException $e) { abort(409, $e->getMessage()); }
            $resolution = DB::table('ppl_ap_reconciliations')->where('supplier_credit_binding_id', $bindingId)->lockForUpdate()->first();
            abort_if($resolution && !in_array($resolution->status, ['proposed', 'approved'], true), 409, 'Supplier credit attribution state is unresolved.');
            $original = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $binding->journal_id)->lockForUpdate()->first();
            $lines = ProtectedJournalQuery::table('journal_details')->where('journal_id', $binding->journal_id)->orderBy('id')->lockForUpdate()->get();
            $snapshot = ['binding' => (array) $binding, 'header' => (array) $original,
                'lines' => $lines->map(fn ($line) => (array) $line)->all(), 'reconciliation' => $resolution ? (array) $resolution : null];
            $identity = 'supplier-credit-reversal:'.$bindingId;
            $journal = PostingService::post(['transaction_date' => $data['posting_date'], 'source_doc_no' => $identity,
                'bill_id' => $billId, 'currency_code' => 'IDR', 'journal_type' => 'REVERSAL',
                'transaction_type' => 'Supplier Price Credit Reversal', 'notes' => $data['reason']],
                $lines->map(fn ($line) => ['account_code' => $line->account_code, 'position' => $line->position === 'DEBET' ? 'KREDIT' : 'DEBET',
                    'amount' => (string) $line->amount, 'currency_code' => 'IDR'])->all(), null, null,
                ['idempotency_key' => $identity, 'source_type' => 'AP_SUPPLIER_PRICE_CREDIT_REVERSAL', 'source_id' => (string) $bindingId, 'actor_id' => (string) $actor->id]);
            DB::table('accounting_posting_intents')->where('journal_id', $journal->getKey())->update(['reversal_of' => $binding->journal_id]);
            $id = DB::table('supplier_credit_reversals')->insertGetId(['binding_id' => $bindingId, 'journal_id' => $journal->getKey(),
                'reconciliation_id' => $resolution?->id, 'reversed_by' => $actor->id, 'status' => 'reversed',
                'posting_date' => $data['posting_date'], 'reason' => $data['reason'], 'payload_hash' => $hash,
                'correction_amount' => $resolution?->status === 'approved' ? $resolution->amount : '0.00',
                'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now()]);
            if ($resolution) DB::table('ppl_ap_reconciliation_decisions')->insert(['reconciliation_id' => $resolution->id,
                'actor_id' => $actor->id, 'action' => 'credit_reversed', 'reason' => $data['reason'], 'created_at' => now()]);
            return DB::table('supplier_credit_reversals')->where('id', $id)->sole();
        });
    }

    private static function payloadHash(int $binding, string $date, string $reason): string
    {
        return hash('sha256', json_encode([$binding, $date, $reason], JSON_THROW_ON_ERROR));
    }

    /** Read-only proof used by retries and residual projection; never trust a status alone. */
    public static function valid(object $binding, object $reversal): bool
    {
        if ($reversal->status !== 'reversed' || (int) $reversal->binding_id !== (int) $binding->id
            || !hash_equals($reversal->payload_hash, self::payloadHash((int) $binding->id, substr($reversal->posting_date, 0, 10), $reversal->reason))
            || !SupplierCreditPosting::sealed($binding, true)) return false;
        $snapshot = json_decode($reversal->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
        $header = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $binding->journal_id)->first();
        $lines = ProtectedJournalQuery::table('journal_details')->where('journal_id', $binding->journal_id)->orderBy('id')->get();
        $resolution = DB::table('ppl_ap_reconciliations')->where('supplier_credit_binding_id', $binding->id)->first();
        if (($snapshot['binding'] ?? null) !== (array) $binding || ($snapshot['header'] ?? null) !== (array) $header
            || ($snapshot['lines'] ?? null) !== $lines->map(fn ($line) => (array) $line)->all()
            || ($snapshot['reconciliation'] ?? null) !== ($resolution ? (array) $resolution : null)
            || (int) ($reversal->reconciliation_id ?? 0) !== (int) ($resolution->id ?? 0)
            || bccomp((string) $reversal->correction_amount, $resolution?->status === 'approved' ? (string) $resolution->amount : '0.00', 2) !== 0) return false;
        $identity = 'supplier-credit-reversal:'.$binding->id;
        $intent = DB::table('accounting_posting_intents')->where('journal_id', $reversal->journal_id)
            ->where('idempotency_key', $identity)->where('source_type', 'AP_SUPPLIER_PRICE_CREDIT_REVERSAL')
            ->where('source_id', (string) $binding->id)->where('actor_id', $reversal->reversed_by)
            ->where('reversal_of', $binding->journal_id)->whereNotNull('sealed_at')->first();
        $reverse = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $reversal->journal_id)->first();
        if (!$intent || !$reverse || $reverse->journal_type !== 'REVERSAL' || $reverse->source_doc_no !== $identity
            || (int) $reverse->bill_id !== (int) $binding->purchase_bill_id || $reverse->currency_code !== 'IDR'
            || substr($reverse->transaction_date, 0, 10) !== substr($reversal->posting_date, 0, 10) || $reverse->notes !== $reversal->reason
            || DB::table('accounting_posting_intents')->where('reversal_of', $reversal->journal_id)->exists()) return false;
        $actual = ProtectedJournalQuery::table('journal_details')->where('journal_id', $reversal->journal_id)->orderBy('id')->get();
        if ($actual->count() !== $lines->count()) return false;
        foreach ($lines as $index => $line) {
            $opposite = $actual[$index];
            if ($opposite->account_code !== $line->account_code || $opposite->currency_code !== 'IDR'
                || $opposite->position !== ($line->position === 'DEBET' ? 'KREDIT' : 'DEBET')
                || bccomp((string) $opposite->amount, (string) $line->amount, 2) !== 0) return false;
        }
        return true;
    }
}