<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanWorkflowStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_retry_conflict_and_failed_transition_are_atomic(): void
    {
        config(['payment_plan_v33.enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-approval-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        $payload = $fixture['cases'][0]['given'];
        $payload['id'] = 'c12c61c0-e501-4d22-96c3-905183c31adc';
        $payload['createdBy'] = (string) $user->id;
        DB::table('ppl_requests')->insert(['id' => $payload['id'], 'company_id' => $company->id, 'owner_user_id' => $user->id,
            'doc' => $payload['doc'], 'status' => 'draft', 'version' => 1, 'payload' => json_encode($payload)]);
        $actor = ['id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'company_id' => $company->id, 'department' => 'purchasing', 'roles' => ['requester']];
        $store = new WorkflowStore(fn () => new \DateTimeImmutable('2026-10-27T12:00:00Z'));
        $first = $store->action($payload['id'], 1, 'submit-one', 'submit', [], $actor);
        $this->assertSame('submitted', $first['request']['status']);
        $this->assertSame(2, $first['version']);
        $this->assertSame($first, $store->action($payload['id'], 1, 'submit-one', 'submit', [], $actor));
        foreach ([
            [1, 'submit-one', 'submit', ['reason' => 'changed'], 'mutation_conflict'],
            [1, 'submit-two', 'submit', [], 'stale_version'],
            [2, 'approve-one', 'approve_request', ['checked' => true], 'role_denied'],
            [2, 'paid-one', 'confirm_paid', [], 'financial_adapter_required'],
        ] as [$version, $mutation, $action, $input, $expected]) {
            try {
                $store->action($payload['id'], $version, $mutation, $action, $input, $actor);
                $this->fail('Invalid workflow mutation accepted.');
            } catch (RuleViolation $exception) {
                $this->assertSame($expected, $exception->rule);
            }
        }
        $this->assertDatabaseCount('ppl_events', 1);
        $this->assertDatabaseHas('ppl_requests', ['id' => $payload['id'], 'version' => 2, 'status' => 'submitted']);
        $this->assertDatabaseCount('journal_headers', 0);
    }
}