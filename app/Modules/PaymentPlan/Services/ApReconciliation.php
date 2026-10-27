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
            'amount' => 'required|decimal:0,2|min:0.01', 'reason' => 'required|string|min:5|max:2000', 'evidence' => 'required|string|min:5|max:500'])->validate();
        return DB::transaction(function () use ($company, $data) {
            LegacyFinancialFence::lock();
            $bill = PurchaseBill::whereKey($data['purchase_bill_id'])->lockForUpdate()->firstOrFail();
            $snapshot = $this->source($data['journal_id'], $bill);
            $id = DB::table('ppl_ap_reconciliations')->insertGetId($data + ['company_id' => $company, 'proposed_by' => auth()->id(),
                'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($id, 'proposed', $data['reason']);
            return $id;
        });
    }

    public function decide(int $id, int $version, string $action, string $reason): void
    {
        abort_unless(in_array($action, ['approve', 'cancel'], true), 422);
        $company = $this->company($action);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:5|max:2000'])->validate();
        DB::transaction(function () use ($company, $id, $version, $action, $reason) {
            LegacyFinancialFence::lock();
            $row = DB::table('ppl_ap_reconciliations')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($row->version !== $version) $this->fail('stale_version');
            $bill = PurchaseBill::whereKey($row->purchase_bill_id)->lockForUpdate()->firstOrFail();
            if ($action === 'approve') {
                if ($row->status !== 'proposed' || (int) $row->proposed_by === auth()->id()) $this->fail('independent_approval_required');
                $source = $this->source($row->journal_id, $bill);
                if (json_encode($source, JSON_THROW_ON_ERROR) !== $row->source_snapshot) $this->fail('source_changed');
                $used = (string) DB::table('ppl_ap_reconciliations')->where('journal_id', $row->journal_id)->where('status', 'approved')->sum('amount');
                if (bccomp(bcadd($used, (string) $row->amount, 2), $source['capacity'], 2) > 0) $this->fail('source_overallocated');
                $projection = (new PurchaseBillAllocation())->source($bill->id, $company);
                if (bccomp((string) $row->amount, $projection['residual'], 2) > 0) $this->fail('invoice_overallocated');
            } elseif ($row->status !== 'approved') $this->fail('approved_resolution_required');
            if ($action === 'cancel' && DB::table('ppl_ap_allocations')->where('purchase_bill_id', $bill->id)->where('status', 'active')->exists()) $this->fail('active_payment_reservation');
            DB::table('ppl_ap_reconciliations')->where('id', $id)->update(['status' => $action === 'approve' ? 'approved' : 'cancelled',
                'approved_by' => auth()->id(), 'version' => $version + 1, 'updated_at' => now()]);
            $this->audit($id, $action, $reason);
        });
    }

    private function source(string $journalId, PurchaseBill $bill): array
    {
        $header = ProtectedJournalQuery::table('journal_headers')->where('journal_id', $journalId)->lockForUpdate()->first();
        if (!$header || $journalId === $bill->journal_id || $header->bill_id || $header->purch_ret_id || ($header->currency_code ?? 'IDR') !== 'IDR') $this->fail('unattributed_idr_journal_required');
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
        return ['account' => $bill->credit_account, 'capacity' => $capacity, 'header' => (array) $header, 'lines' => $lines->map(fn ($l) => (array) $l)->all()];
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