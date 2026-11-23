<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use Illuminate\Support\Facades\DB;

/** Explainable projection; unknown links are exceptions, not inferred allocations. */
final class ApResidualProjection
{
    public function project(PurchaseBill $bill, string $account, string $total): array
    {
        $events = [];
        $exceptions = [];
        $used = '0.00';
        $represented = [];
        $resolved = [];
        $reversedCredits = [];
        foreach (DB::table('supplier_credit_reversals as r')->join('supplier_credit_bindings as b', 'b.id', '=', 'r.binding_id')
            ->where('b.purchase_bill_id', $bill->id)->select('r.*')->get() as $reversal) {
            $binding = DB::table('supplier_credit_bindings')->where('id', $reversal->binding_id)->first();
            $resolved[$binding->journal_id] = $resolved[$reversal->journal_id] = true;
            $reversedCredits[$binding->id] = true;
            if (!SupplierCreditReversal::valid($binding, $reversal)) {
                $exceptions[] = ['source' => 'supplier_credit_reversal:'.$reversal->id, 'reason' => 'supplier_credit_reversal_invalid'];
                continue;
            }
            if (bccomp((string) $reversal->correction_amount, '0', 2) > 0) {
                $events[] = ['source' => 'reconciliation:'.$reversal->reconciliation_id, 'journal_id' => $binding->journal_id,
                    'status' => 'approved', 'reduction' => (string) $reversal->correction_amount];
            }
            $events[] = ['source' => 'supplier_credit_reversal:'.$reversal->id, 'journal_id' => $reversal->journal_id,
                'status' => 'reversed', 'reduction' => bcsub('0', (string) $reversal->correction_amount, 2)];
        }
        // Legacy allocations have a different lifecycle/ownership contract from R1.
        // Until that contract is reconciled, never silently expose their invoice as payable.
        $legacyPayments = DB::table('transaksi_payment_plan')->where(function ($query) use ($bill) {
            $query->where('ref_bill_number', $bill->bill_number)
                ->orWhereIn('id_payment', DB::table('bill_payment_allocations')->where('purchase_bill_id', $bill->id)->select('id_payment'));
        })->where(function ($query) use ($bill) {
            $query->where('status_payment', 'POSTED')
                ->orWhereIn('id_payment', DB::table('bill_payment_allocations')->where('purchase_bill_id', $bill->id)->select('id_payment'));
        })->orderBy('id_payment')->get();
        foreach ($legacyPayments as $payment) {
            $exceptions[] = ['source' => 'legacy_payment:'.$payment->id_payment,
                'reason' => 'legacy_payment_reconciliation_required', 'journal_id' => $payment->journal_id,
                'status' => $payment->status_payment,
                'allocations' => DB::table('bill_payment_allocations')->where('id_payment', $payment->id_payment)->orderBy('id')->get()->toArray()];
        }
        foreach (DB::table('bill_payment_allocations')->where('purchase_bill_id', $bill->id)
            ->whereNotIn('id_payment', DB::table('transaksi_payment_plan')->select('id_payment'))->get() as $allocation) {
            $exceptions[] = ['source' => 'legacy_allocation:'.$allocation->id, 'reason' => 'orphan_legacy_payment_allocation'];
        }
        foreach (DB::table('ppl_ap_reconciliations')->where('purchase_bill_id', $bill->id)->where('status', 'approved')->get() as $resolution) {
            if (isset($reversedCredits[$resolution->supplier_credit_binding_id])) continue;
            $snapshot = json_decode($resolution->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if ($resolution->supplier_credit_binding_id) {
                $binding = DB::table('supplier_credit_bindings')->where('id', $resolution->supplier_credit_binding_id)->first();
                if (!$binding || (int) $binding->company_id !== (int) $resolution->company_id || (int) $binding->purchase_bill_id !== (int) $bill->id
                    || $binding->journal_id !== $resolution->journal_id || (array) $binding !== ($snapshot['supplier_credit'] ?? null)
                    || !SupplierCreditPosting::sealed($binding)) {
                    $exceptions[] = ['source' => 'reconciliation:'.$resolution->id, 'reason' => 'approved_source_changed'];
                    $resolved[$resolution->journal_id] = true;
                    continue;
                }
            }
            $header = \App\Support\ProtectedJournalQuery::table('journal_headers')->where('journal_id', $resolution->journal_id)->first();
            $lines = \App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $resolution->journal_id)->orderBy('id')->get()->map(fn ($l) => (array) $l)->all();
            $correction = DB::table('purchase_return_ap_corrections as c')
                ->join('purchase_return_reversals as rv', 'rv.id', '=', 'c.reversal_id')
                ->join('purchase_returns as r', 'r.id', '=', 'rv.purchase_return_id')
                ->join('accounting_posting_intents as i', 'i.journal_id', '=', 'rv.journal_id')
                ->where('c.reconciliation_id', $resolution->id)->where('c.purchase_bill_id', $bill->id)
                ->where('rv.original_journal_id', $resolution->journal_id)->whereColumn('i.reversal_of', 'rv.original_journal_id')
                ->whereNotNull('i.sealed_at')->where('r.status', 'REVERSED')->first(['c.id', 'c.amount', 'rv.purchase_return_id', 'rv.journal_id']);
            $currentReturn = isset($snapshot['return'])
                ? (array) DB::table('purchase_returns')->where('id', $snapshot['return']['id'])->first() : [];
            $corrected = $correction && isset($snapshot['return'], $snapshot['return_detail'])
                && (int) $correction->purchase_return_id === (int) $snapshot['return']['id']
                && bccomp((string) $correction->amount, (string) $resolution->amount, 2) === 0;
            if ($corrected) {
                $currentReturn['status'] = $snapshot['return']['status'];
                $currentReturn['updated_at'] = $snapshot['return']['updated_at'];
            }
            $returnChanged = isset($snapshot['return_detail']) && (
                $snapshot['return_detail'] !== (array) DB::table('purchase_return_details')->where('id', $resolution->purchase_return_detail_id)->first()
                || $snapshot['return'] !== $currentReturn);
            if (!$header || $snapshot['header'] !== (array) $header || $snapshot['lines'] !== $lines || $snapshot['account'] !== $account || $returnChanged) {
                $exceptions[] = ['source' => 'reconciliation:'.$resolution->id, 'reason' => 'approved_source_changed'];
                $resolved[$resolution->journal_id] = true;
                continue;
            }
            $events[] = ['source' => 'reconciliation:'.$resolution->id, 'journal_id' => $resolution->journal_id, 'status' => 'approved', 'reduction' => (string) $resolution->amount];
            $used = bcadd($used, (string) $resolution->amount, 2);
            if ($corrected) {
                $events[] = ['source' => 'return_ap_correction:'.$correction->id, 'journal_id' => $correction->journal_id,
                    'status' => 'posted', 'reduction' => bcsub('0', (string) $correction->amount, 2)];
                $used = bcsub($used, (string) $correction->amount, 2);
            }
            $resolved[$resolution->journal_id] = true;
        }
        foreach (DB::table('ppl_ap_allocations')->where('purchase_bill_id', $bill->id)->orderBy('request_id')->get() as $allocation) {
            $amount = in_array($allocation->status, ['active', 'consumed'], true) ? (string) $allocation->amount : '0.00';
            $events[] = ['source' => 'ppl_allocation:'.$allocation->request_id, 'status' => $allocation->status, 'reduction' => $amount];
            $used = bcadd($used, $amount, 2);
            $payment = DB::table('ppl_payment_events')->where('request_id', $allocation->request_id)->first();
            if ($payment) {
                if ($payment->journal_id) $represented[$payment->journal_id] = true;
                $intent = DB::table('accounting_posting_intents')->where('idempotency_key', 'ppl:paid:'.$payment->id)->value('journal_id');
                if ($intent) $represented[$intent] = true;
            }
        }
        // Only explicit invoice identity permits automatic attribution. Document text is a candidate, not proof.
        $journals = \App\Support\ProtectedJournalQuery::table('journal_headers')->where(function ($q) use ($bill) {
            $q->where('bill_id', $bill->id)->orWhere('source_doc_no', $bill->bill_number);
        })->orderBy('journal_id')->get();
        foreach ($journals as $journal) {
            if ($journal->journal_id === $bill->journal_id || isset($represented[$journal->journal_id])) continue;
            if (isset($resolved[$journal->journal_id])) continue;
            // Bound supplier credits are recognized only through their single approved reconciliation.
            $credit = DB::table('supplier_credit_bindings')->where('journal_id', $journal->journal_id)->first();
            if ($credit) {
                if ((int) $credit->purchase_bill_id !== (int) $bill->id || !SupplierCreditPosting::sealed($credit)) {
                    $exceptions[] = ['source' => 'journal:'.$journal->journal_id, 'reason' => 'supplier_credit_binding_invalid'];
                }
                continue;
            }
            $lines = \App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $journal->journal_id)->get();
            $reduction = '0.00';
            foreach ($lines->where('account_code', $account) as $line) {
                $reduction = $line->position === 'DEBET' ? bcadd($reduction, (string) $line->amount, 2) : bcsub($reduction, (string) $line->amount, 2);
            }
            if (bccomp($reduction, '0', 2) === 0) continue;
            $reason = null;
            if ((int) $journal->bill_id !== (int) $bill->id) $reason = 'invoice_identity_unverified';
            elseif (($journal->currency_code ?? 'IDR') !== 'IDR' || $lines->contains(fn ($l) => ($l->currency_code ?? 'IDR') !== 'IDR')) $reason = 'ap_currency_reconciliation_required';
            elseif (!\App\Support\JournalBalanceValidator::isBalanced($lines->map(fn ($line) => (array) $line)->all())) $reason = 'invalid_ap_adjustment_journal';
            if ($reason) {
                $exceptions[] = ['source' => 'journal:'.$journal->journal_id, 'reason' => $reason, 'candidate_reduction' => $reduction];
                continue;
            }
            $events[] = ['source' => 'journal:'.$journal->journal_id, 'status' => 'posted', 'reduction' => $reduction];
            $used = bcadd($used, $reduction, 2);
        }
        if ($bill->purchase_order_id) {
            foreach (DB::table('purchase_returns')->where('purchase_order_id', $bill->purchase_order_id)->whereNotIn('status', ['VOID', 'REJECT', 'REJECTED'])->get() as $return) {
                if ($return->status === 'REVERSED' && DB::table('purchase_return_reversals')->where('purchase_return_id', $return->id)->exists()) continue;
                $details = DB::table('purchase_return_details')->where('purchase_return_id', $return->id)->get();
                $complete = $details->isNotEmpty();
                $allocated = '0.00';
                foreach ($details as $detail) {
                    $amount = '0.00';
                    $quantity = '0.000000';
                    foreach (DB::table('ppl_ap_reconciliations')->where('purchase_return_detail_id', $detail->id)->where('status', 'approved')->get() as $allocation) {
                        $basis = json_decode($allocation->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                        $sourceHeader = \App\Support\ProtectedJournalQuery::table('journal_headers')->where('journal_id', $allocation->journal_id)->first();
                        $sourceLines = \App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $allocation->journal_id)->orderBy('id')->get()->map(fn ($l) => (array) $l)->all();
                        if (($basis['return_detail'] ?? null) !== (array) $detail || ($basis['return'] ?? null) !== (array) $return
                            || !$sourceHeader || $basis['header'] !== (array) $sourceHeader || $basis['lines'] !== $sourceLines) { $complete = false; continue; }
                        $amount = bcadd($amount, (string) $allocation->amount, 2);
                        $quantity = bcadd($quantity, (string) $allocation->quantity, 6);
                    }
                    if (bccomp($amount, (string) $detail->subtotal, 2) !== 0 || bccomp($quantity, (string) $detail->qty_returned, 6) !== 0) $complete = false;
                    $allocated = bcadd($allocated, $amount, 2);
                }
                if (!$complete || bccomp($allocated, (string) $return->total_return_amount, 2) !== 0) {
                    $exceptions[] = ['source' => 'purchase_return:'.$return->id, 'reason' => 'return_invoice_allocation_required',
                        'candidate_reduction' => (string) $return->total_return_amount, 'allocated' => $allocated, 'details' => $details->toArray()];
                }
            }
        }
        $residual = bcsub($total, $used, 2);
        if (bccomp($residual, '0', 2) < 0 || bccomp($residual, $total, 2) > 0) $exceptions[] = ['source' => 'purchase_bill:'.$bill->id, 'reason' => 'ap_residual_out_of_bounds'];
        return ['residual' => $residual, 'events' => $events, 'exceptions' => $exceptions];
    }
}