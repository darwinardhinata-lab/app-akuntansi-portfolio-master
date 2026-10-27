<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Support\ErpActor;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentPlanErpActorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment_plan_v33.enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        Route::middleware('web')->get('/_test/ppl-actor', fn (\Illuminate\Http\Request $request) => response()->json(app(ErpActor::class)->resolve($request)));
    }

    public function test_sites_headers_do_not_authenticate_and_admin_title_does_not_grant_payment_role(): void
    {
        $this->withHeaders(['oai-authenticated-user-id' => 'director', 'oai-authenticated-user-email' => 'director@example.test'])
            ->getJson('/_test/ppl-actor')->assertUnauthorized();
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create(['role' => 'ADMIN']);
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->actingAs($user)->getJson('/_test/ppl-actor')->assertForbidden();
        $role = Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->getJson('/_test/ppl-actor')->assertOk()->assertJsonPath('roles', ['requester'])->assertJsonPath('id', (string) $user->id);
    }

    public function test_revoked_membership_and_disabled_module_fail_closed(): void
    {
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $role = Role::create(['code' => 'PPL_DIRECTOR', 'name' => 'Director']);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user)->withSession([CompanyContext::SESSION_KEY => $company->id])->getJson('/_test/ppl-actor')->assertStatus(409);
        config(['payment_plan_v33.enabled' => false]);
        $this->getJson('/_test/ppl-actor')->assertNotFound();
    }
}