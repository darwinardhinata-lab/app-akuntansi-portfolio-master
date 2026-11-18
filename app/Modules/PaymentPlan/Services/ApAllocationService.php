<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\Platform\Models\Company;
use App\Support\ProtectedJournalQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** R2 inspection adapter. R1 remains the sole reservation/payment posting authority. */
final class ApAllocationService
{
    public function validateThreeWayMatch(int $invoiceId, int $companyId): array
    {
        return DB::transaction(function () use ($invoiceId, $companyId) {
            $bill = $this->bill($invoiceId, $companyId);
            // R2 needs explicit journal currency evidence. A stale migration ledger
            // cannot authorize guessing IDR or backfilling legacy base-ledger history.
            $schema = Schema::connection($bill->getConnectionName());
            if (!$schema->hasColumn('journal_headers', 'currency_code')
                || !$schema->hasColumn('journal_details', 'currency_code')) {
                throw new RuleViolation('ap_currency_schema_required', 409);
            }
            $source = (new PurchaseBillAllocation())->source($invoiceId, $companyId);
            $exceptions = $source['exceptions'];
            $add = function (string $reason, string $source) use (&$exceptions): void {
                $exceptions[] = ['source' => $source, 'reason' => $reason];
            };
            if (ProtectedJournalQuery::table('journal_headers')->where('journal_id', $bill->journal_id)->value('currency_code') !== 'IDR'
                || ProtectedJournalQuery::table('journal_details')->where('journal_id', $bill->journal_id)->where(function ($query) {
                    $query->whereNull('currency_code')->orWhere('currency_code', '<>', 'IDR');
                })->exists()) {
                $add('non_idr_currency_not_supported', 'journal:'.$bill->journal_id);
            }
            $po = $bill->purchase_order_id
                ? DB::table('purchase_orders')->where('id', $bill->purchase_order_id)->lockForUpdate()->first() : null;
            if (!$po || (int) $po->company_id !== $companyId) {
                $add('company_purchase_order_required', 'purchase_bill:'.$invoiceId);
            } else {
                // A GRN for another invoice on this PO is not evidence for this bill.
                $receipts = DB::table('purchase_receipts')->where('company_id', $companyId)
                    ->where('purchase_order_id', $po->id)->where('purchase_bill_id', $invoiceId)
                    ->where('status', 'POSTED')->lockForUpdate()->get();
                if ($receipts->isEmpty()) $add('no_posted_bill_grn', 'purchase_bill:'.$invoiceId);
                $quantities = [];
                $total = '0.00';
                foreach ($receipts as $receipt) {
                    if ($receipt->journal_id !== $bill->journal_id) $add('grn_journal_mismatch', 'grn:'.$receipt->id);
                    $lines = DB::table('purchase_receipt_details')->where('purchase_receipt_id', $receipt->id)->lockForUpdate()->get();
                    if ($lines->isEmpty()) $add('empty_grn', 'grn:'.$receipt->id);
                    foreach ($lines as $line) {
                        $poLine = DB::table('purchase_order_details')->where('id', $line->purchase_order_detail_id)
                            ->where('purchase_order_id', $po->id)->lockForUpdate()->first();
                        if (!$poLine || $poLine->item_code !== $line->item_code || $poLine->product_id !== $line->product_id) {
                            $add('po_detail_identity_mismatch', 'grn_line:'.$line->id);
                            continue;
                        }
                        if (bccomp((string) $line->qty_received, '0', 6) <= 0) $add('invalid_received_quantity', 'grn_line:'.$line->id);
                        $quantities[$poLine->id] = bcadd($quantities[$poLine->id] ?? '0', (string) $line->qty_received, 6);
                        // Compare actual decimal differences, not bccomp's -1/0/1 result.
                        $difference = ltrim(bcsub((string) $poLine->price, (string) $line->unit_cost, 2), '-');
                        if (bccomp($difference, (string) $bill->match_tolerance_price, 2) > 0) $add('price_tolerance_exceeded', 'grn_line:'.$line->id);
                        if (bccomp((string) $line->amount, bcmul((string) $line->qty_received, (string) $line->unit_cost, 2), 2) !== 0) {
                            $add('grn_line_total_mismatch', 'grn_line:'.$line->id);
                        }
                        $total = bcadd($total, (string) $line->amount, 2);
                    }
                }
                foreach ($quantities as $id => $quantity) {
                    $ordered = (string) DB::table('purchase_order_details')->where('id', $id)->value('qty');
                    // Partial receipts are valid; only over-receipt exceeds the PO quantity.
                    if (bccomp($quantity, bcadd($ordered, (string) $bill->match_tolerance_qty, 6), 6) > 0) $add('qty_tolerance_exceeded', 'po_line:'.$id);
                }
                if ($receipts->isNotEmpty() && bccomp($total, (string) $bill->grand_total, 2) !== 0) $add('bill_grn_total_mismatch', 'purchase_bill:'.$invoiceId);
            }
            // Read-only: no match flags, waivers or audit mutations.
            return ['matched' => !$exceptions, 'match_status' => $exceptions ? 'exception' : 'matched', 'exceptions' => $exceptions];
        });
    }

    public function calculateOutstanding(int $invoiceId, int $companyId): string
    {
        return DB::transaction(function () use ($invoiceId, $companyId) {
            $this->bill($invoiceId, $companyId);
            $source = (new PurchaseBillAllocation())->source($invoiceId, $companyId);
            if (isset($source['blocked'])) throw new RuleViolation('ap_reconciliation_required', 409);
            return $source['residual'];
        });
    }

    private function bill(int $invoiceId, int $companyId): PurchaseBill
    {
        // Legacy bills/journals have no company_id. Match the R1 sole-MGI boundary.
        if (Company::count() !== 1 || !Company::whereKey($companyId)->where('code', 'MGI')->where('active', true)->exists()) {
            throw new RuleViolation('ap_company_binding_required', 409);
        }
        return PurchaseBill::whereKey($invoiceId)->lockForUpdate()->firstOrFail();
    }

    public function allocatePayment(array $data): array
    {
        throw new RuleViolation('r1_payment_workflow_required', 409);
    }

    public function postAllocationJournal(int $allocationId, int $companyId, User $actor): string
    {
        throw new RuleViolation('r1_payment_workflow_required', 409);
    }

    public function reverseAllocation(int $allocationId, int $companyId, User $actor, string $reason): void
    {
        // Never zero an amount or release a consumed payment without reversal evidence.
        throw new RuleViolation('r1_payment_workflow_required', 409);
    }

    public function waiveMatch(int $invoiceId, int $companyId, User $actor, string $reason): void
    {
        throw new RuleViolation('ap_match_waiver_workflow_required', 409);
    }
}