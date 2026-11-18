<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\Product;
use App\Modules\PaymentPlan\Services\LegacyFinancialFence;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Support\OperationalCompany;
use App\Support\AccountingPeriodGuard;
use App\Support\PostingService;
use App\Support\ProtectedJournalQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PurchaseReturnReversal
{
    public function reverse(Request $request, int $id, ?string $date, string $reason): object
    {
        return DB::transaction(function () use ($request, $id, $date, $reason) {
            abort_unless(config('platform.order_company_scope_enabled') && config('platform.posting_intents_enabled')
                && config('payment_plan_v33.bank_workflow'), 409, 'A2, bank workflow and posting intents are required.');
            LegacyFinancialFence::lock(true);
            $company = app(OperationalCompany::class)->authorizeRequest($request);
            $actor = $request->user()->fresh();
            abort_unless($actor && $actor->role === 'FINANCE' && in_array((string) $actor->id,
                array_map('strval', config('platform.payment_reverse_user_ids', [])), true), 403);

            // Take the period mutex before PO / stock locks, like the create writers.
            AccountingPeriodGuard::lock();
            $poId = DB::table('purchase_returns')->where('id', $id)->value('purchase_order_id');
            abort_unless($poId && DB::table('purchase_orders')->where('id', $poId)->where('company_id', $company->id)->exists(), 404);
            app(PurchaseReturnEligibility::class)->lockOrder($company->id, (string) $poId);
            $return = DB::table('purchase_returns')->where('id', $id)->where('purchase_order_id', $poId)->lockForUpdate()->first();
            abort_unless($return, 404);
            $previous = DB::table('purchase_return_reversals')->where('purchase_return_id', $id)->lockForUpdate()->first();
            if ($previous) {
                abort_unless($return->status === 'REVERSED' && ($date ?? $previous->posting_date) === $previous->posting_date
                    && $reason === $previous->reason && (int) $previous->actor_id === (int) $actor->id,
                    409, 'Reversal payload conflicts with the recorded reversal.');
                return $previous;
            }
            $date ??= now($company->timezone ?: 'Asia/Jakarta')->toDateString();
            abort_unless($return->status === 'COMPLETED' && $date >= $return->return_date, 409, 'Return status or reversal date is not eligible.');
            try {
                AccountingPeriodGuard::dates([$date]);
            } catch (\RuntimeException $e) {
                abort(409, $e->getMessage());
            }

            $bills = DB::table('purchase_bills')->where('purchase_order_id', $poId)->limit(2)->lockForUpdate()->get();
            abort_unless($bills->count() === 1 && $bills->first()->payment_status === 'UNPAID'
                && $bills->first()->journal_id, 409, 'Exactly one posted unpaid invoice is required.');
            $bill = $bills->first();
            abort_unless(DB::table('purchase_bills')->where('journal_id', $bill->journal_id)->limit(2)->lockForUpdate()->get(['id'])->count() === 1,
                409, 'Invoice journal is shared or ambiguous.');
            $journals = ProtectedJournalQuery::table('journal_headers')->where('source_doc_no', $return->return_number)->limit(2)->lockForUpdate()->get();
            $journal = $journals->first();
            abort_unless($journals->count() === 1 && $journal->transaction_type === 'Purchase Return'
                && $journal->journal_id !== $bill->journal_id && !$journal->bill_id && !$journal->payment_id
                && (!$journal->purch_ret_id || (int) $journal->purch_ret_id === $id)
                && ($journal->currency_code ?? 'IDR') === 'IDR' && substr($journal->transaction_date, 0, 10) === $return->return_date,
                409, 'Unambiguous original IDR return journal is required.');
            abort_if(DB::table('purchase_bills')->where('journal_id', $journal->journal_id)->exists()
                || DB::table('purchase_receipts')->where('journal_id', $journal->journal_id)->exists()
                || DB::table('ppl_payment_events')->where('journal_id', $journal->journal_id)->exists()
                || DB::table('accounting_posting_intents')->where('reversal_of', $journal->journal_id)->exists(),
                409, 'Return journal is shared or already reversed.');
            $details = DB::table('purchase_return_details')->where('purchase_return_id', $id)->limit(2)->lockForUpdate()->get();
            abort_unless($details->count() === 1 && $details->first()->product_id, 409, 'Only a single proven stock line is supported.');
            $detail = $details->first();
            $sourceLine = DB::table('purchase_order_details')->where('purchase_order_id', $poId)
                ->where('item_code', $detail->item_code)->limit(2)->lockForUpdate()->get();
            abort_unless($sourceLine->count() === 1 && (int) $sourceLine->first()->product_id === (int) $detail->product_id
                && (int) $sourceLine->first()->qty_received >= (int) $detail->qty_returned,
                409, 'Return does not match a unique received PO line.');
            $attributions = DB::table('ppl_ap_reconciliations')->whereIn('status', ['proposed', 'approved'])
                ->where(fn ($q) => $q->where('journal_id', $journal->journal_id)->orWhere('purchase_return_detail_id', $detail->id)
                    ->orWhere('purchase_bill_id', $bill->id))->limit(2)->lockForUpdate()->get();
            $attribution = $attributions->first();
            if ($attribution) {
                abort_unless($attributions->count() === 1 && $attribution->status === 'approved'
                    && (int) $attribution->company_id === (int) $company->id
                    && (int) $attribution->purchase_bill_id === (int) $bill->id
                    && (int) $attribution->purchase_return_detail_id === (int) $detail->id
                    && $attribution->journal_id === $journal->journal_id
                    && bccomp((string) $attribution->quantity, (string) $detail->qty_returned, 6) === 0
                    && bccomp((string) $attribution->amount, (string) $detail->subtotal, 2) === 0
                    && bccomp((string) $attribution->amount, (string) $return->total_return_amount, 2) === 0,
                    409, 'Only one full approved AP attribution is eligible; active proposals or ambiguous relations are not supported.');
            }
            $this->assertUnpaid($bill, $company->id, $id, $attribution);

            $product = Product::whereKey($detail->product_id)->lockForUpdate()->first();
            $movements = InventoryLedger::where('evidence_number', $return->return_number)->limit(2)->lockForUpdate()->get();
            $out = $movements->first();
            abort_unless($product && $movements->count() === 1 && $out->type === 'OUT'
                && (int) $out->product_id === (int) $product->id && $out->transaction_date->toDateString() === $return->return_date
                && bccomp($out->qty, (string) $detail->qty_returned, 4) === 0 && bccomp($out->qty, '0', 4) > 0
                && bccomp($out->running_qty, (string) $product->stock_quantity, 4) === 0
                && bccomp($out->moving_average_cost, (string) $product->average_cost, 2) === 0
                && !InventoryLedger::where('product_id', $product->id)->where('id', '>', $out->id)->lockForUpdate()->first(),
                409, 'Stock provenance changed or downstream movements exist.');
            $lines = ProtectedJournalQuery::table('journal_details')->where('journal_id', $journal->journal_id)->lockForUpdate()->get();
            $debit = $lines->firstWhere('position', 'DEBET');
            $credit = $lines->firstWhere('position', 'KREDIT');
            abort_unless($lines->count() === 2 && $debit && $credit && $debit->account_code === $bill->credit_account
                && $credit->account_code === config('coa.persediaan') && $credit->account_code !== $debit->account_code
                && !$lines->contains(fn ($l) => ($l->currency_code ?? 'IDR') !== 'IDR')
                && bccomp((string) $debit->amount, (string) $return->total_return_amount, 2) === 0
                && bccomp((string) $credit->amount, $out->total_cost, 2) === 0
                && bccomp((string) $debit->amount, (string) $credit->amount, 2) === 0
                && bccomp((string) $credit->amount, '0', 2) > 0, 409, 'Only balanced inventory/AP returns without variance are supported.');

            $evidence = 'PR-REV-'.$id;
            abort_if(InventoryLedger::where('evidence_number', $evidence)->exists()
                || ProtectedJournalQuery::table('journal_headers')->where('source_doc_no', $evidence)->exists(), 409, 'Reversal identity already in use.');
            $qty = bcadd($out->running_qty, $out->qty, 4);
            $value = bcadd($out->running_value, $out->total_cost, 2);
            $average = bcdiv(bcadd(bcdiv($value, $qty, 6), '0.005', 6), '1', 2);
            $product->update(['stock_quantity' => $qty, 'average_cost' => $average]);
            InventoryLedger::create(['transaction_date' => $date, 'evidence_number' => $evidence, 'product_id' => $product->id,
                'type' => 'IN', 'qty' => $out->qty, 'unit_cost' => $out->unit_cost, 'total_cost' => $out->total_cost,
                'running_qty' => $qty, 'running_value' => $value, 'moving_average_cost' => $average,
                'description' => 'Purchase return compensation for stock movement '.$out->id]);
            $reversal = PostingService::post(['transaction_date' => $date, 'journal_type' => 'REVERSAL',
                'source_doc_no' => $evidence, 'purch_ret_id' => $id, 'transaction_type' => 'Purchase Return Reversal', 'notes' => $reason],
                $lines->map(fn ($line) => ['account_code' => $line->account_code, 'amount' => $line->amount,
                    'position' => $line->position === 'DEBET' ? 'KREDIT' : 'DEBET'])->all(), null, null,
                ['idempotency_key' => $evidence, 'source_type' => 'PURCHASE_RETURN_REVERSAL', 'source_id' => (string) $id, 'actor_id' => (string) $actor->id]);
            DB::table('accounting_posting_intents')->where('journal_id', $reversal->getKey())->update(['reversal_of' => $journal->journal_id]);
            $reversalId = DB::table('purchase_return_reversals')->insertGetId(['purchase_return_id' => $id, 'actor_id' => $actor->id,
                'original_journal_id' => $journal->journal_id, 'journal_id' => $reversal->getKey(), 'evidence_number' => $evidence,
                'posting_date' => $date, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
            if ($attribution) {
                DB::table('purchase_return_ap_corrections')->insert(['reversal_id' => $reversalId, 'reconciliation_id' => $attribution->id,
                    'purchase_bill_id' => $bill->id, 'amount' => $attribution->amount, 'created_at' => now()]);
            }
            DB::table('purchase_returns')->where('id', $id)->update(['status' => 'REVERSED', 'updated_at' => now()]);
            return DB::table('purchase_return_reversals')->where('purchase_return_id', $id)->first();
        });
    }

    private function assertUnpaid(object $bill, int $company, int $returnId, ?object $attribution): void
    {
        abort_if(DB::table('ppl_ap_allocations')->where('purchase_bill_id', $bill->id)->whereIn('status', ['active', 'consumed'])
            ->lockForUpdate()->get(['request_id'])->isNotEmpty(), 409, 'Invoice has payment or reservation evidence.');
        $requests = DB::table('ppl_requests')->where('company_id', $company)->where('payload->purchaseBillId', $bill->id)->select('id');
        abort_if(DB::table('ppl_reservations')->whereIn('request_id', clone $requests)->whereIn('status', ['active', 'consumed'])->exists()
            || DB::table('ppl_payment_events')->whereIn('request_id', $requests)->exists(), 409, 'Invoice has payment or reservation evidence.');
        abort_if(DB::table('transaksi_payment_plan')->where('ref_bill_number', $bill->bill_number)
            ->whereIn('status_payment', ['APPROVED', 'PAID', 'POSTED'])->exists(), 409, 'Invoice has open legacy payment evidence.');
        try {
            $source = app(PurchaseBillAllocation::class)->source($bill->id, $company);
        } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $e) {
            abort(409, $e->rule);
        }
        $target = $attribution ? 'reconciliation:'.$attribution->id : null;
        $events = collect($source['events']);
        $expected = bcsub($source['total'], (string) ($attribution->amount ?? '0'), 2);
        abort_if(bccomp($source['residual'], $expected, 2) !== 0
            || ($attribution && $events->where('source', $target)->count() !== 1)
            || $events->contains(fn ($e) => $e['source'] !== $target && bccomp($e['reduction'], '0', 2) !== 0)
            || collect($source['exceptions'])->contains(fn ($e) => $e['reason'] !== 'return_invoice_allocation_required'
                || $e['source'] !== 'purchase_return:'.$returnId || $attribution !== null), 409, 'Invoice has unresolved AP evidence outside this return.');
    }
}