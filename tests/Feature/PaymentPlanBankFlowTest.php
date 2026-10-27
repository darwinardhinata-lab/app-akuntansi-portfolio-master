<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanBankFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_decisions_reserve_consume_and_post_exactly_once(): void
    {
        config(['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.reservations' => true, 'payment_plan_v33.posting' => true, 'platform.posting_intents_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-approval-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        $payload = $fixture['cases'][0]['given'];
        $payload['id'] = '45e52737-dd50-4996-af16-b704e54f6375';
        $actors = [];
        foreach (['req' => 'requester', 'dir' => 'director', 'fs' => 'finance_staff', 'mk' => 'maker', 'gm' => 'general_manager', 'acc' => 'accountant'] as $key => $role) {
            $user = User::factory()->create();
            $actors[$key] = ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email,
                'department' => 'purchasing', 'company_id' => $company->id, 'roles' => [$role]];
        }
        $payload['createdBy'] = $actors['req']['id'];
        $payload['attachments'] = [['id' => 'settle', 'kind' => 'settlement'], ['id' => 'payment', 'kind' => 'payment']];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($fixture['cases'][0]['settings'])]);
        DB::table('ppl_requests')->insert(['id' => $payload['id'], 'company_id' => $company->id, 'owner_user_id' => $payload['createdBy'],
            'doc' => $payload['doc'], 'status' => 'draft', 'version' => 1, 'payload' => json_encode($payload)]);
        foreach (['expense' => ['610001', 'DEBET'], 'bank.idr3' => ['111003', 'DEBET']] as $semantic => [$code, $normal]) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic '.$semantic, 'coa_type' => 'Other', 'normal_balance' => $normal, 'report_pos' => 'NERACA']);
            DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.'.$semantic, 'account_code' => $code, 'active' => true]);
        }
        $store = new WorkflowStore(fn () => new \DateTimeImmutable('2026-10-27T12:00:00Z'));
        $steps = [
            ['submit', 'req', []], ['approve_request', 'dir', ['checked' => true]],
            ['prepare_settlement', 'fs', ['checked' => true, 'base' => '1000000.00', 'coa' => '610001', 'sourceId' => 'idr3']],
            ['approve_settlement', 'dir', ['checked' => true]], ['mark_maker', 'mk', ['reference' => 'BANK-ONE']],
            ['release_gm', 'gm', ['checked' => true, 'reference' => 'RELEASE-ONE']],
            ['confirm_paid', 'fs', ['checked' => true, 'reference' => 'DEBIT-ONE', 'paidDate' => '2026-10-27']],
            ['post_external', 'acc', ['checked' => true, 'reference' => 'ERP-ACK']],
        ];
        foreach ($steps as $index => [$action, $key, $input]) {
            $result = $store->action($payload['id'], $index + 1, 'bank-step-'.$index, $action, $input, $actors[$key]);
            if ($action === 'mark_maker') {
                $this->assertDatabaseHas('ppl_reservations', ['request_id' => $payload['id'], 'status' => 'active']);
                // A competing instruction cannot reuse the original 1.5m opening after 1m is reserved.
                $competitor = $result['request'];
                $competitor['id'] = 'another-instruction';
                $competitor['_company_id'] = $company->id;
                try {
                    (new \App\Modules\PaymentPlan\Services\BankPaymentControl())->before($competitor, 'mark_maker', [], $fixture['cases'][0]['settings']);
                    $this->fail('Reservation overspend accepted.');
                } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $exception) {
                    $this->assertSame('funds_insufficient', $exception->rule);
                }
                try {
                    $store->action($payload['id'], $index + 2, 'unsafe-return', 'return', ['reason' => 'No evidence'], $actors['fs']);
                    $this->fail('Return released active instruction.');
                } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $exception) {
                    $this->assertSame('verified_no_debit_required', $exception->rule);
                }
            }
            if ($action === 'confirm_paid') {
                $this->assertSame('paid', $result['request']['status']);
                $this->assertDatabaseCount('journal_headers', 0);
            }
        }
        $this->assertSame('posted', $result['request']['status']);
        $this->assertNotEmpty($result['request']['journal']['journalId']);
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseCount('ppl_payment_events', 1);
        $this->assertDatabaseHas('ppl_reservations', ['request_id' => $payload['id'], 'status' => 'consumed']);
        $retry = $store->action($payload['id'], 8, 'bank-step-7', 'post_external', ['checked' => true, 'reference' => 'ERP-ACK'], $actors['acc']);
        $this->assertSame($result, $retry);
        $this->assertDatabaseCount('journal_headers', 1);
    }
}