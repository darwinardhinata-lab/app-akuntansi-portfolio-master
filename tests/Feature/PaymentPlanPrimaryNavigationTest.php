<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentPlanPrimaryNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_menu_uses_existing_panel_gate_and_preserves_legacy(): void
    {
        config(['platform.filament_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false, 'payment_plan_v33.enabled' => true,
            'payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.primary_navigation' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $requester = Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        $panel = Role::create(['code' => 'DEMO_PANEL', 'name' => 'Panel']);
        $panel->permissions()->attach(Permission::create(['code' => 'erp.panel.access', 'resource' => 'erp.panel', 'action' => 'access'])->id);
        $authorized = User::factory()->create();
        $denied = User::factory()->create();
        $roleOnly = User::factory()->create();
        foreach ([$authorized, $denied, $roleOnly] as $user) {
            $user->companies()->attach($company->id, ['is_default' => true]);
        }
        $authorized->platformRoles()->attach([$requester->id, $panel->id], ['company_id' => $company->id]);
        $denied->platformRoles()->attach($panel->id, ['company_id' => $company->id]);
        $roleOnly->platformRoles()->attach($requester->id, ['company_id' => $company->id]);

        $workspace = route('payment-plan-v33.workspace');
        $this->actingAs($authorized)->get(route('dashboard.index'))->assertOk()->assertSee('href="'.$workspace.'"', false);
        $this->get($workspace)->assertRedirect('/erp-panel/bank-payments');
        $this->get('/erp-panel/bank-payments')->assertOk()->assertSee('bankInboxPanel')->assertSee(route('payment.index'));

        foreach ([$denied, $roleOnly] as $user) {
            $this->actingAs($user)->get(route('dashboard.index'))->assertOk()->assertDontSee('href="'.$workspace.'"', false);
            $this->get($workspace)->assertForbidden();
            $this->get('/erp-panel/bank-payments')->assertForbidden();
        }
        config(['payment_plan_v33.primary_navigation' => false]);
        $this->actingAs($authorized)->get(route('dashboard.index'))->assertOk()->assertDontSee('href="'.$workspace.'"', false)
            ->assertSee('href="'.route('payment.index').'"', false);
    }
}