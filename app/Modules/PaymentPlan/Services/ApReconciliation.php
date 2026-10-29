<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use App\Support\ProtectedJournalQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ApReconciliation
{
    private function company(string $action): int
    {
        abort_unless(config('payment_plan_v33.bank_workflow') && app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'ap.reconcile.'.$action), 403);
        return (int) app(CompanyContext::class)->requireCompany(request())->id;
    }

    public function propose(array $input): int
    {
        $company = $this->company('propose');
        $data = Validator::make($input, ['purchase_bill_id' => 'required|integer', 'journal_id' => 'required|string|max:50',
            'amount' => 'required|decimal:0,2|min:0.01', 'reason' => 'required|string|min:5|max:2000', 'evidence' => 'required|string|min:5|max:500',
            'purchase_return_detail_id' => 'nullable|integer|min:1', 'quantity' => 'required_with:purchase_return_detail_id|nullable|decimal:0,6|min:0.000001'])->validate();
        if (empty($data['purchase_return_detail_id']) && !empty($data['quantity'])) $this->fail('return_detail_required');
        $data['purchase_return_detail_id'] = ($data['purchase_return_detail_id'] ?? null) ?: null;
        $data['quantity'] = $data['quantity'] ?? null;
        return DB::transaction(function () use ($company, $data) {
            LegacyFinancialFence::lock(true);
            $bill = PurchaseBill::whereKey($data['purchase_bill_id'])->lockForUpdate()->firstOrFail();
            $snapshot = $this->source($data['journal_id'], $bill, $data['purchase_return_detail_id'] ?? null);
            $this->validateReturnAmount($data, $snapshot);
            $id = DB::table('ppl_ap_reconciliations')->insertGetId($data + ['company_id' => $company, 'proposed_by' => auth()->id(),
                'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($id, 'proposed', $data['reason']);
            return $id;
        });
    }

    /** Read-only advisory capacity; propose/approve always revalidate under the fence. */
    public function previewSource(string $journalId, PurchaseBill $bill, ?int $returnDetailId = null): array
    {
        $this->company('view');
        $source = $this->source($journalId, $bill, $returnDetailId);
        $used = '0.00';
        foreach (DB::table('ppl_ap_reconciliations')->where('journal_id', $journalId)->where('status', 'approved')->get() as $row) {
            $snapshot = json_decode($row->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
            if ($snapshot['account'] === $source['account']) $used = bcadd($used, (string) $row->amount, 2);
        }
        return ['remaining' => bcsub($source['capacity'], $used, 2)];
    }

    public function decide(int $id, int $version, string $action, string $reason): void
    {
        abort_unless(in_array($action, ['approve', 'cancel'], true), 422);
        $company = $this->company($action);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:2000'])->validate();
        DB::transaction(function () use ($company, $id, $version, $action, $reason) {
            LegacyFinancialFence::lock(true);
            $row = DB::table('ppl_ap_reconciliations')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->version !== $version) $this->fail('stale_version');
            $bill = PurchaseBill::whereKey($row->purchase_bill_id)->lockForUpdate()->firstOrFail();
            if ($action === 'approve') {
                if ($row->status !== 'proposed' || (int) $row->proposed_by === auth()->id()) $this->fail('independent_approval_required');
                $source = $this->source($row->journal_id, $bill, $row->purchase_return_detail_id);
                if (json_encode($source, JSON_THROW_ON_ERROR) !== $row->source_snapshot) $this->fail('source_changed');
                $this->validateReturnAmount((array) $row, $source);
                $used = '0.00';
                foreach (DB::table('ppl_ap_reconciliations')->where('journal_id', $row->journal_id)->where('status', 'approved')->get() as $approved) {
                    $basis = json_decode($approved->source_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    if ($basis['account'] === $source['account']) $used = bcadd($used, (string) $approved->amount, 2);
                }
                if (bccomp(bcadd($used, (string) $row->amount, 2), $source['capacity'], 2) > 0) $this->fail('source_overallocated');
                if ($row->purchase_return_detail_id) {
                    $quantity = '0.000000';
                    foreach (DB::table('ppl_ap_reconciliations')->where('purchase_return_detail_id', $row->purchase_return_detail_id)->where('status', 'approved')->pluck('quantity') as $allocatedQuantity) {
                        $quantity = bcadd($quantity, (string) $allocatedQuantity, 6);
                    }
                    if (bccomp(bcadd($quantity, (string) $row->quantity, 6), (string) $source['return_detail']['qty_returned'], 6) > 0) $this->fail('return_quantity_overallocated');
                }
                $projection = (new PurchaseBillAllocation())->source($bill->id, $company);
                if (bccomp((string) $row->amount, $projection['residual'], 2) > 0) $this->fail('invoice_overallocated');
            } else {
                $affected = $row->purchase_return_detail_id
                    ? PurchaseBill::where('purchase_order_id', $bill->purchase_order_id)->pluck('id')->all() : [$bill->id];
                if ($row->status !== 'approved' || DB::table('ppl_ap_allocations')->whereIn('purchase_bill_id', $affected)->where('status', 'active')->exists()) $this->fail('active_payment_reservation');
            }
            DB::table('ppl_ap_reconciliations')->where('id', $id)->update(['status' => $action === 'approve' ? 'approved' : 'cancelled',
                'approved_by' => auth()->id(), 'version' => $version + 1, 'updated_at' => now()]);
            $this->audit($id, $action, $reason);
        });
    }

    private function source(string $journalId, PurchaseBill $bill, ?int $returnDetailId = null): array
    {
        $returnSnapshot = [];
        if ($returnDetailId) {
            $detail = DB::table('purchase_return_details')->where('id', $returnDetailId)->lockForUpdate()->first();
            $return = $detail ? DB::table('purchase_returns')->where('id', $detail->purchase_return_id)->lockForUpdate()->first() : null;
            if (!$return || $return->status !== 'COMPLETED' || !$bill->purchase_order_id || (int) $return->purchase_order_id !== (int) $bill->purchase_order_id) $this->fail('return_invoice_po_mismatch');
            $returnSnapshot = ['return' => (array) $return, 'return_detail' => (array) $detail];
        }
        $header = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $journalId)->lockForUpdate()->first();
        if (!$header || $journalId === $bill->journal_id || $header->bill_id || ($header->currency_code ?? 'IDR') !== 'IDR') $this->fail('unattributed_idr_journal_required');
        if ($returnDetailId) {
            if ($header->source_doc_no !== $return->return_number || ($header->purch_ret_id && (int) $header->purch_ret_id !== (int) $return->id)
                || ProtectedJournalQuery::table('journal_headers')->where('source_doc_no', $return->return_number)->count() !== 1) $this->fail('unique_return_journal_required');
        } elseif ($header->purch_ret_id || DB::table('purchase_returns')->where('return_number', $header->source_doc_no)->exists()) $this->fail('return_detail_allocation_required');
        if (DB::table('ppl_payment_events')->where('journal_id', $journalId)->exists()
            || DB::table('accounting_posting_intents')->where('journal_id', $journalId)->where('source_type', 'PPL_PAYMENT_EVENT')->exists()) $this->fail('already_represented_payment');
        $lines = ProtectedJournalQuery::table('journal_details')->where('journal_id', $journalId)->orderBy('id')->lockForUpdate()->get();
        if (!\App\Support\JournalBalanceValidator::isBalanced($lines->map(fn ($l) => (array) $l)->all())) $this->fail('unbalanced_source');
        $capacity = '0.00';
        foreach ($lines as $line) {
            if (($line->currency_code ?? 'IDR') !== 'IDR' || bccomp((string) $line->amount, '0', 2) <= 0) $this->fail('invalid_source_line');
            if ($line->account_code === $bill->credit_account) $capacity = $line->position === 'DEBET' ? bcadd($capacity, (string) $line->amount, 2) : bcsub($capacity, (string) $line->amount, 2);
        }
        if (bccomp($capacity, '0', 2) <= 0) $this->fail('positive_ap_reduction_required');
        return ['account' => $bill->credit_account, 'capacity' => $capacity, 'header' => (array) $header, 'lines' => $lines->map(fn ($l) => (array) $l)->all()] + $returnSnapshot;
    }

    private function validateReturnAmount(array $data, array $source): void
    {
        if (!isset($source['return_detail'])) return;
        $detail = $source['return_detail'];
        if (bccomp((string) $detail['price'], '0', 2) <= 0 || bccomp((string) $detail['qty_returned'], '0', 6) <= 0
            || bccomp(bcmul((string) $detail['price'], (string) $detail['qty_returned'], 2), (string) $detail['subtotal'], 2) !== 0
            || bccomp(bcmul((string) $detail['price'], (string) $data['quantity'], 2), (string) $data['amount'], 2) !== 0) $this->fail('return_quantity_value_mismatch');
    }

    private function audit(int $id, string $action, string $reason): void
    {
        DB::table('ppl_ap_reconciliation_decisions')->insert(['reconciliation_id' => $id, 'actor_id' => auth()->id(), 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
    }

    private function fail(string $reason): never
    {
        throw ValidationException::withMessages(['reconciliation' => $reason]);
    }
}