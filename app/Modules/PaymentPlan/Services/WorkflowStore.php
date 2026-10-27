<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Domain\V33ApprovalDomain;
use Closure;
use Illuminate\Support\Facades\DB;

/** Workflow persistence only. Financial actions require the reservation/posting adapter. */
final class WorkflowStore
{
    public function __construct(private readonly Closure $clock)
    {
    }

    public function action(string $id, int $expectedVersion, string $mutationId, string $action, array $input, array $actor): array
    {
        if (!config('payment_plan_v33.enabled', false)) throw new RuleViolation('disabled', 404);
        // Fail closed until shared residual, bank, cash and report locks are implemented.
        $financial = in_array($action, BankPaymentControl::ACTIONS, true);
        if (!$financial && !in_array($action, ['submit', 'approve_request', 'ack_manager', 'ack_gm', 'reject', 'return', 'correction'], true)) throw new RuleViolation('financial_adapter_required', 409);
        if ($financial && !config('payment_plan_v33.bank_workflow', false)) {
            throw new RuleViolation('financial_adapter_required', 409);
        }
        if ($mutationId === '' || strlen($mutationId) > 100) throw new RuleViolation('invalid_mutation');
        return DB::transaction(function () use ($id, $expectedVersion, $mutationId, $action, $input, $actor, $financial) {
            // Global company fence precedes request locks for every enabled bank mutation.
            $control = new BankPaymentControl();
            $settings = config('payment_plan_v33.bank_workflow', false) ? $control->lockContext($actor['company_id']) : [];
            $row = DB::table('ppl_requests')->where('company_id', $actor['company_id'])->where('id', $id)->lockForUpdate()->first();
            if (!$row) throw new RuleViolation('not_found', 404);
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            $domain = new V33ApprovalDomain($this->clock);
            if (!$domain->canSee($actor, $payload)) throw new RuleViolation('forbidden', 403);
            ksort($input);
            $hash = hash('sha256', json_encode([$id, $expectedVersion, $action, $input, $actor['id']], JSON_THROW_ON_ERROR));
            $event = DB::table('ppl_events')->where('company_id', $actor['company_id'])->where('mutation_id', $mutationId)->lockForUpdate()->first();
            if ($event) {
                if (!hash_equals($event->payload_hash, $hash)) throw new RuleViolation('mutation_conflict', 409);
                return json_decode($event->result, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($row->version !== $expectedVersion) throw new RuleViolation('stale_version', 409);
            if ($action === 'return' && DB::table('ppl_reservations')->where('request_id', $id)->where('status', 'active')->exists()) {
                throw new RuleViolation('verified_no_debit_required', 409);
            }
            // All same-company requests are explicit context. No browser-supplied ledger/settings.
            $all = DB::table('ppl_requests')->where('company_id', $actor['company_id'])->get()->map(fn ($r) => json_decode($r->payload, true, 512, JSON_THROW_ON_ERROR))->all();
            // Approval/return of allocated sources also needs shared residual locking; do not imply protection.
            if (in_array($payload['category'], ['asset', 'raw_dp'], true) || ($payload['category'] === 'raw_ap' && !config('payment_plan_v33.ap_allocations', false))) throw new RuleViolation('allocation_adapter_required', 409);
            if ($financial) $control->before($payload + ['_company_id' => $actor['company_id']], $action, $all, $settings);
            $result = $domain->transition($payload, $action, $input, $actor, $all, $settings);
            if ($financial) $result = $control->after($payload, $result, $action, $actor);
            $version = $row->version + 1;
            $response = ['request' => $result, 'version' => $version];
            DB::table('ppl_requests')->where('id', $id)->update(['payload' => json_encode($result, JSON_THROW_ON_ERROR), 'status' => $result['status'], 'version' => $version, 'updated_at' => now()]);
            DB::table('ppl_events')->insert([
                'company_id' => $actor['company_id'], 'request_id' => $id, 'actor_user_id' => $actor['id'],
                'mutation_id' => $mutationId, 'payload_hash' => $hash, 'event' => $action, 'version' => $version,
                'result' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            return $response;
        }, 3);
    }
}