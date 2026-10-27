<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class BankRequestStore
{
    public function create(array $input, array $actor): array
    {
        if (!config('payment_plan_v33.enabled') || !config('payment_plan_v33.bank_workflow')) throw new RuleViolation('disabled', 404);
        if (!array_intersect($actor['roles'], ['requester', 'finance_staff', 'finance_manager'])) throw new RuleViolation('role_denied', 403);
        $data = Validator::make($input, [
            'id' => 'required|uuid', 'payee' => 'required|string|max:150', 'subject' => 'required|array|size:3',
            'subject.*' => 'required|string|max:1000', 'purpose' => 'required|array|size:3', 'purpose.*' => 'required|string|max:1000',
            'amount' => 'required|decimal:0,2|min:0.01|max:1000000000000', 'recipientBank' => 'required|string|max:100',
            'recipientAccount' => 'required|string|max:100', 'due' => 'required|date_format:Y-m-d',
            'purchaseBillId' => 'sometimes|integer|min:1',
        ])->validate();
        return DB::transaction(function () use ($data, $actor) {
            (new BankPaymentControl())->lockContext($actor['company_id']);
            $existing = DB::table('ppl_requests')->where('id', $data['id'])->first();
            if ($existing) throw new RuleViolation('request_exists', 409);
            $date = now('Asia/Jakarta')->toDateString();
            $payload = $data + [
                'doc' => 'PP-'.strtoupper(str_replace('-', '', $data['id'])), 'createdBy' => $actor['id'], 'createdName' => $actor['name'],
                'department' => $actor['department'], 'currency' => 'IDR', 'route' => 'bank', 'category' => 'services', 'stage' => 'direct',
                'requestDate' => $date, 'createdAt' => now()->toISOString(), 'status' => 'draft', 'attachments' => [], 'decisions' => [], 'history' => [],
                'po' => '', 'invoice' => '', 'capex' => '', 'referenceTotal' => '0.00', 'priorPaid' => '0.00', 'advanceReference' => '', 'notes' => '', 'cycle' => '',
                'lines' => [['id' => (string) Str::uuid(), 'description' => $data['subject'], 'qty' => 1, 'unitPrice' => $data['amount'], 'total' => $data['amount'], 'evidence' => '']],
            ];
            if (isset($data['purchaseBillId'])) {
                if (!config('payment_plan_v33.ap_allocations', false)) throw new RuleViolation('allocation_adapter_required', 409);
                $source = (new PurchaseBillAllocation())->source((int) $data['purchaseBillId'], $actor['company_id']);
                if (bccomp((string) $data['amount'], $source['residual'], 2) > 0) throw new RuleViolation('overpayment');
                $payload['category'] = 'raw_ap';
                $payload['payee'] = $source['payee'];
                $payload['invoice'] = $source['number'];
                $payload['referenceTotal'] = $source['total'];
                $payload['priorPaid'] = '0.00';
            }
            DB::table('ppl_requests')->insert(['id' => $data['id'], 'company_id' => $actor['company_id'], 'owner_user_id' => $actor['id'],
                'doc' => $payload['doc'], 'status' => 'draft', 'version' => 1, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ppl_events')->insert(['company_id' => $actor['company_id'], 'request_id' => $data['id'], 'actor_user_id' => $actor['id'],
                'mutation_id' => 'create:'.$data['id'], 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                'event' => 'create', 'version' => 1, 'result' => json_encode(['request' => $payload, 'version' => 1], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            return ['request' => $payload, 'version' => 1];
        });
    }
}