<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\PaymentBalances;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\Platform\Models\CompanyCoaMapping;
use App\Support\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BankPaymentControl
{
    public const ACTIONS = ['prepare_settlement', 'approve_settlement', 'mark_maker', 'release_gm', 'hold_bank', 'bank_attempt', 'restart_after_no_debit', 'confirm_paid', 'post_external'];

    public function lockContext(int $company): array
    {
        $row = DB::table('ppl_financial_contexts')->where('company_id', $company)->lockForUpdate()->first();
        if (!$row) throw new RuleViolation('financial_configuration_required', 409);
        $settings = json_decode($row->settings, true, 512, JSON_THROW_ON_ERROR);
        if (config('payment_plan_v33.erp_bank_balance', false)) {
            $settings['_erp_projection'] = [];
            foreach ($settings['accounts'] as &$account) {
                if (!isset(config('payment_plan_v33.payment_accounts')[$account['id']])) continue;
                $projection = (new ErpBankBalance())->project($company, $account, $settings);
                $settings['_erp_projection'][$account['id']] = $projection;
                // Domain subtracts paid requests below; add them back to this adapter seed only.
                $paid = '0.00';
                foreach (DB::table('ppl_requests')->where('company_id', $company)->get() as $request) {
                    $p = json_decode($request->payload, true, 512, JSON_THROW_ON_ERROR);
                    if (in_array($p['status'], ['paid', 'posted'], true) && ($p['settlement']['sourceId'] ?? null) === $account['id'] && $p['paidDate'] >= $settings['erp_cutoff']) $paid = bcadd($paid, (string) $p['settlement']['debit'], 2);
                }
                $account['opening'] = bcadd($projection['actual'], $paid, 2);
                $account['asOf'] = $settings['erp_cutoff'];
            }
            unset($account);
            $settings['receipts'] = []; // All receipts already represented by GL; never count twice.
        }
        return $settings;
    }

    public function before(array $request, string $action, array $all, array $settings): void
    {
        if ($request['route'] !== 'bank' || $request['currency'] !== 'IDR') throw new RuleViolation('bank_idr_adapter_required', 409);
        if (!config('payment_plan_v33.reservations', false)) throw new RuleViolation('reservation_control_required', 409);
        \App\Support\AccountingPeriodGuard::source([$request['requestDate']]);
        // Initial direct-expense slice: AP/DP requires authoritative ID allocations, never fallback to expense.
        $ap = $request['category'] === 'raw_ap' && config('payment_plan_v33.ap_allocations', false);
        if (in_array($request['category'], ['asset', 'raw_dp', 'petty_topup'], true) || ($request['category'] === 'raw_ap' && !$ap) || $request['stage'] !== 'direct') throw new RuleViolation('allocation_adapter_required', 409);
        if ($action === 'mark_maker') {
            if ($ap) (new PurchaseBillAllocation())->reserve($request, $request['_company_id']);
            $balances = new PaymentBalances();
            $account = $balances->source($request, $settings);
            $available = $balances->account($account, $all, $settings);
            if (isset($settings['_erp_projection'][$account['id']])) $available = $settings['_erp_projection'][$account['id']]['actual'];
            if ($available === null) throw new RuleViolation('balance_required');
            $reserved = '0.00';
            foreach (DB::table('ppl_reservations')->where('company_id', $request['_company_id'])->where('account_key', $account['id'])->where('status', 'active')->get() as $row) {
                $reserved = bcadd($reserved, (string) $row->amount, 2);
            }
            if (bccomp(bcsub($available, $reserved, 2), (string) $request['settlement']['debit'], 2) < 0) throw new RuleViolation('funds_insufficient');
        }
        if (in_array($action, ['release_gm', 'hold_bank', 'confirm_paid', 'restart_after_no_debit'], true)) {
            $reservation = DB::table('ppl_reservations')->where('request_id', $request['id'])->where('status', 'active')->first();
            if (!$reservation || bccomp((string) $reservation->amount, (string) $request['settlement']['debit'], 2) !== 0) throw new RuleViolation('active_reservation_required', 409);
        }
    }

    public function after(array $original, array $result, string $action, array $actor): array
    {
        if ($action === 'confirm_paid') \App\Support\AccountingPeriodGuard::source([$result['paidDate']]);
        if ($action === 'mark_maker') {
            DB::table('ppl_reservations')->updateOrInsert(['request_id' => $result['id']], [
                'company_id' => $actor['company_id'], 'account_key' => $result['settlement']['sourceId'],
                'currency' => $result['currency'], 'amount' => $result['settlement']['debit'], 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Hold/pending/failed never release a reservation.
        if ($action === 'restart_after_no_debit') {
            DB::table('ppl_reservations')->where('request_id', $result['id'])->update(['status' => 'released', 'updated_at' => now()]);
            DB::table('ppl_ap_allocations')->where('request_id', $result['id'])->update(['status' => 'released', 'updated_at' => now()]);
        }
        if ($action === 'confirm_paid') {
            DB::table('ppl_reservations')->where('request_id', $result['id'])->update(['status' => 'consumed', 'updated_at' => now()]);
            DB::table('ppl_ap_allocations')->where('request_id', $result['id'])->update(['status' => 'consumed', 'updated_at' => now()]);
            DB::table('ppl_payment_events')->insert([
                'id' => (string) Str::uuid(), 'request_id' => $result['id'], 'company_id' => $actor['company_id'],
                'confirmed_by' => $actor['id'], 'snapshot' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
        }
        if ($action === 'post_external') {
            if (!config('payment_plan_v33.posting', false)) throw new RuleViolation('posting_disabled', 409);
            $event = DB::table('ppl_payment_events')->where('company_id', $actor['company_id'])->where('request_id', $result['id'])->lockForUpdate()->first();
            if (!$event) throw new RuleViolation('confirmed_event_required', 409);
            $frozen = json_decode($event->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $settlement = $frozen['settlement'];
            $lines = [];
            $ap = $frozen['category'] === 'raw_ap';
            $entries = [['expense', 'DEBET', $settlement['base']], ['vat', 'DEBET', $settlement['vat']]];
            if ($ap) {
                $allocation = DB::table('ppl_ap_allocations')->where('company_id', $actor['company_id'])->where('request_id', $frozen['id'])->where('status', 'consumed')->lockForUpdate()->first();
                if (!$allocation || bccomp((string) $allocation->amount, (string) $settlement['gross'], 2) !== 0) throw new RuleViolation('consumed_ap_allocation_required', 409);
                // Payment is already real. Do not re-resolve a mutable invoice/status for its counterpart.
                $source = $allocation->source_snapshot ? json_decode($allocation->source_snapshot, true, 512, JSON_THROW_ON_ERROR) : null;
                if (!is_array($source) || (int) ($source['id'] ?? 0) !== (int) $allocation->purchase_bill_id
                    || (int) ($frozen['purchaseBillId'] ?? 0) !== (int) $allocation->purchase_bill_id
                    || empty($source['account_code']) || empty($source['journal_id'])) {
                    throw new RuleViolation('ap_source_snapshot_reconciliation_required', 409);
                }
                $lines[] = ['account_code' => $source['account_code'], 'position' => 'DEBET', 'amount' => (string) $allocation->amount];
                $entries = []; // Invoice recognition already booked expense/VAT.
            }
            foreach (array_merge($entries, [['fees', 'DEBET', $settlement['fees']],
                ['withholding', 'KREDIT', $settlement['withholding']], ['bank.'.$settlement['sourceId'], 'KREDIT', $settlement['debit']]]) as [$semantic, $side, $amount]) {
                if (bccomp((string) $amount, '0', 2) === 0) continue;
                $code = CompanyCoaMapping::where('company_id', $actor['company_id'])->where('semantic_key', 'ppl.'.$semantic)->where('active', true)->value('account_code');
                if (!$code || !DB::table('accounts')->where('account_code', $code)->exists()) throw new RuleViolation('approved_coa_mapping_required', 409);
                if ($semantic === 'expense' && $code !== $settlement['coa']) throw new RuleViolation('settlement_coa_mapping_conflict', 409);
                $lines[] = ['account_code' => $code, 'position' => $side, 'amount' => (string) $amount];
            }
            $journal = PostingService::post([
                'transaction_date' => $frozen['paidDate'], 'journal_type' => 'PAYMENT', 'source_doc_no' => $frozen['doc'],
                'notes' => 'Payment event '.$event->id,
            ], $lines, null, null, [
                'idempotency_key' => 'ppl:paid:'.$event->id, 'source_type' => 'PPL_PAYMENT_EVENT', 'source_id' => $event->id,
                'actor_id' => (string) $event->confirmed_by,
            ]);
            DB::table('ppl_payment_events')->where('id', $event->id)->update(['journal_id' => $journal->getKey()]);
            $result['journal']['journalId'] = $journal->getKey();
            $result['journal']['paymentEventId'] = $event->id;
        }
        return $result;
    }
}