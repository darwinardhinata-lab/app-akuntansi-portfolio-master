<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanWorkflowHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_http_submit_retry_conflict_and_spoofed_identity(): void
    {
        config(['payment_plan_v33.enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $url = route('payment-plan-v33.action');
        $this->withHeaders(['oai-authenticated-user-id' => 'fake'])->postJson($url, [])->assertUnauthorized();
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $role = Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-approval-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        $payload = $fixture['cases'][0]['given'];
        $payload['id'] = '5e29a18d-21d6-43f5-8f64-64e6468c77db';
        $payload['createdBy'] = (string) $user->id;
        DB::table('ppl_requests')->insert(['id' => $payload['id'], 'company_id' => $company->id, 'owner_user_id' => $user->id,
            'doc' => $payload['doc'], 'status' => 'draft', 'version' => 1, 'payload' => json_encode($payload)]);
        $input = ['id' => $payload['id'], 'version' => 1, 'mutationId' => 'http-submit', 'action' => 'submit', 'input' => []];
        $this->actingAs($user)->postJson($url, $input)->assertOk()->assertJsonPath('version', 2)->assertJsonPath('request.status', 'submitted');
        $this->postJson($url, $input)->assertOk()->assertJsonPath('version', 2);
        $this->postJson($url, array_replace($input, ['mutationId' => 'http-stale']))->assertStatus(409)->assertJsonPath('error', 'stale_version');
        $this->postJson($url, array_replace($input, ['action' => 'approve_request']))->assertStatus(409)->assertJsonPath('error', 'mutation_conflict');
        $this->assertDatabaseCount('ppl_events', 1);
        $this->postJson($url, array_replace($input, ['version' => 2, 'mutationId' => 'deny-approval', 'action' => 'approve_request', 'input' => ['checked' => true]]))
            ->assertForbidden()->assertJsonPath('error', 'role_denied');
        $director = User::factory()->create();
        $director->companies()->attach($company->id, ['is_default' => true]);
        $directorRole = Role::create(['code' => 'PPL_DIRECTOR', 'name' => 'Director']);
        $director->platformRoles()->attach($directorRole->id, ['company_id' => $company->id]);
        $this->actingAs($director)->postJson($url, array_replace($input, ['version' => 2, 'mutationId' => 'director-approval', 'action' => 'approve_request', 'input' => ['checked' => true]]))
            ->assertOk()->assertJsonPath('version', 3)->assertJsonPath('request.status', 'request_approved')->assertJsonPath('request.requestApproval.actorId', (string) $director->id);
        $this->assertDatabaseCount('ppl_events', 2);
        $this->postJson($url, array_replace($input, ['version' => 3, 'mutationId' => 'fake-post', 'action' => 'post_external', 'input' => ['checked' => true, 'reference' => 'FAKE']]))
            ->assertStatus(409)->assertJsonPath('error', 'financial_adapter_required');
        $this->assertDatabaseCount('journal_headers', 0);
        config(['payment_plan_v33.enabled' => false]);
        $this->postJson($url, $input)->assertNotFound();
    }
}