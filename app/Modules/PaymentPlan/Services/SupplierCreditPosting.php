<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Modules\Platform\Support\CompanyCoaRegistry;
use App\Modules\Platform\Support\CompanyContext;
use App\Modules\Platform\Support\OperationalCompany;
use App\Support\AccountingPeriodGuard;
use App\Support\PostingService;
use App\Support\ProtectedJournalQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SupplierCreditPosting
{
    public function post(Request $request, int $billId): object
    {
        $data = $request->validate(['supplier_reference' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9.\/_-]*\z/'],
            'posting_date' => 'required|date_format:Y-m-d', 'amount' => ['required', 'regex:/\A[0-9]{1,14}(?:\.[0-9]{1,2})?\z/'],
            'reason' => 'required|string|min:5|max:255']);
        $amount = bcadd((string) $data['amount'], '0', 2);
        abort_unless(bccomp($amount, '0', 2) > 0, 422, 'A positive credit is required.');
        return DB::transaction(function () use ($request, $billId, $data, $amount) {
            abort_unless(config('platform.order_company_scope_enabled') && config('platform.posting_intents_enabled')
                && config('payment_plan_v33.bank_workflow'), 409);
            LegacyFinancialFence::lock(true);
            $company = app(OperationalCompany::class)->authorizeRequest($request);
            $actor = $request->user()->fresh();
            abort_unless($actor && $actor->role === 'FINANCE'
                && app(CompanyContext::class)->allows($actor, $company, 'supplier_credit.post'), 403);
            // Period mutex precedes purchase, invoice and journal locks.
            AccountingPeriodGuard::lock();
            $bill = PurchaseBill::whereKey($billId)->lockForUpdate()->firstOrFail();
            $party = self::supplier($bill, $company->id);
            $key = ['company_id' => $company->id, 'party_id' => $party, 'supplier_reference' => strtoupper($data['supplier_reference'])];
            $hash = hash('sha256', json_encode([$billId, $data['posting_date'], $amount, $data['reason']], JSON_THROW_ON_ERROR));
            $existing = DB::table('supplier_credit_bindings')->where($key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash) && self::sealed($existing), 409, 'Supplier credit payload conflict or source unavailable.');
                return $existing;
            }
            try { AccountingPeriodGuard::dates([$data['posting_date']]); }
            catch (\RuntimeException $e) { abort(409, $e->getMessage()); }
            $source = self::eligible($bill, $company->id);
            abort_if($source['exceptions'] || bccomp($amount, $source['residual'], 2) > 0, 409, 'Invoice capacity or provenance is unresolved.');
            $kind = CompanyCoaRegistry::SUPPLIER_PRICE_CREDIT;
            $mapping = DB::table('company_coa_mappings')->where('company_id', $company->id)->where('semantic_key', $kind)->lockForUpdate()->first();
            $code = CompanyCoaRegistry::financeMgi()[$kind]['mgi_account'];
            abort_unless($mapping && $mapping->active && $mapping->account_code === $code && $code !== $source['account_code']
                && DB::table('accounts')->where('account_code', $code)->lockForUpdate()->first(), 409, 'Approved MGI supplier credit mapping is required.');
            $identity = 'supplier-credit:'.hash('sha256', json_encode($key, JSON_THROW_ON_ERROR));
            $journal = PostingService::post(['transaction_date' => $data['posting_date'], 'source_doc_no' => $identity,
                'bill_id' => $billId, 'currency_code' => 'IDR', 'notes' => $data['reason'], 'transaction_type' => 'Supplier Price Credit'], [
                ['account_code' => $source['account_code'], 'position' => 'DEBET', 'amount' => $amount, 'currency_code' => 'IDR'],
                ['account_code' => $code, 'position' => 'KREDIT', 'amount' => $amount, 'currency_code' => 'IDR'],
            ], null, null, ['idempotency_key' => $identity, 'source_type' => $kind, 'source_id' => $identity, 'actor_id' => (string) $actor->id]);
            $line = $journal->details()->where('position', 'DEBET')->sole();
            $id = DB::table('supplier_credit_bindings')->insertGetId($key + ['kind' => $kind, 'purchase_bill_id' => $billId,
                'journal_id' => $journal->getKey(), 'ap_line_id' => $line->getKey(), 'posted_by' => $actor->id,
                'payload_hash' => $hash, 'created_at' => now()]);
            return DB::table('supplier_credit_bindings')->where('id', $id)->sole();
        });
    }

    public static function supplier(PurchaseBill $bill, int $company): int
    {
        $po = DB::table('purchase_orders')->where('id', $bill->purchase_order_id)->where('company_id', $company)->lockForUpdate()->first();
        abort_unless($po && $po->party_id && DB::table('parties')->where('id', $po->party_id)->where('company_id', $company)
            ->where('active', true)->lockForUpdate()->first()
            && DB::table('party_roles')->where('party_id', $po->party_id)->where('role', 'SUPPLIER')->where('active', true)->lockForUpdate()->first(),
            409, 'A proven active supplier on the owned PO is required.');
        $receipts = DB::table('purchase_receipts')->where('purchase_bill_id', $bill->id)->limit(2)->lockForUpdate()->get();
        if ($receipts->isNotEmpty()) {
            $receipt = $receipts->first();
            abort_unless($receipts->count() === 1 && $receipt->status === 'POSTED' && $receipt->journal_id === $bill->journal_id
                && (int) $receipt->company_id === $company && (int) $receipt->purchase_order_id === (int) $po->id
                && (int) $receipt->party_id === (int) $po->party_id, 409, 'GRN supplier or invoice provenance is inconsistent.');
        }
        return (int) $po->party_id;
    }

    public static function sealed(object $binding): bool
    {
        return $binding->kind === CompanyCoaRegistry::SUPPLIER_PRICE_CREDIT
            && DB::table('accounting_posting_intents')->where('journal_id', $binding->journal_id)
                ->where('source_type', $binding->kind)->whereNotNull('sealed_at')->whereNull('reversal_of')->exists()
            && !DB::table('accounting_posting_intents')->where('reversal_of', $binding->journal_id)->exists();
    }

    public static function eligible(PurchaseBill $bill, int $company): array
    {
        abort_unless($bill->payment_status === 'UNPAID' && $bill->journal_id
            && DB::table('purchase_bills')->where('journal_id', $bill->journal_id)->limit(2)->lockForUpdate()->get(['id'])->count() === 1, 409, 'A unique posted unpaid invoice is required.');
        $header = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $bill->journal_id)->lockForUpdate()->first();
        abort_unless($header && ($header->currency_code ?? 'IDR') === 'IDR'
            && !ProtectedJournalQuery::table('journal_details')->where('journal_id', $bill->journal_id)->where('currency_code', '<>', 'IDR')->exists(), 409, 'IDR invoice required.');
        $requests = DB::table('ppl_requests')->where('company_id', $company)->where('payload->purchaseBillId', $bill->id)->select('id');
        abort_if(DB::table('ppl_ap_allocations')->where('purchase_bill_id', $bill->id)->whereIn('status', ['active', 'consumed'])->exists()
            || DB::table('ppl_reservations')->whereIn('request_id', clone $requests)->whereIn('status', ['active', 'consumed'])->exists()
            || DB::table('ppl_payment_events')->whereIn('request_id', $requests)->exists()
            || DB::table('transaksi_payment_plan')->where('ref_bill_number', $bill->bill_number)->whereIn('status_payment', ['APPROVED','PAID','POSTED'])->exists(),
            409, 'Invoice has reservation or payment evidence.');
        return app(PurchaseBillAllocation::class)->source($bill->id, $company);
    }
}