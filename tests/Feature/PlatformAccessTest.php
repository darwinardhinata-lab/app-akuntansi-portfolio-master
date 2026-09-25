<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Database\Seeders\PlatformPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Company::create(['code' => 'A', 'name' => 'Company A']);
        $this->b = Company::create(['code' => 'B', 'name' => 'Company B']);
        $this->member = User::factory()->create(['role' => 'ADMIN']);
        $this->member->companies()->attach($this->a->id, ['is_default' => true]);
        $this->seed(PlatformPermissionSeeder::class);
        $this->actingAs($this->member);
    }

    public function test_guest_cannot_open_platform_routes(): void
    {
        auth()->logout();
        $this->get(route('platform.company.edit'))->assertRedirect(route('login'));
        $this->get(route('platform.parties.index'))->assertRedirect(route('login'));
    }

    public function test_company_picker_hides_non_member_company_and_rejects_forgery(): void
    {
        $this->get(route('platform.company.edit'))->assertOk()->assertSee('Company A')->assertDontSee('Company B');
        $this->post(route('platform.company.update'), ['company_id' => $this->b->id])
            ->assertSessionHasErrors('company_id');
        $this->assertNotEquals($this->b->id, session(CompanyContext::SESSION_KEY));
    }

    public function test_member_can_select_company_but_does_not_inherit_a_permissions(): void
    {
        $this->grant(['view']);
        $this->member->companies()->attach($this->b->id);
        $this->post(route('platform.company.update'), ['company_id' => $this->b->id])
            ->assertSessionHas(CompanyContext::SESSION_KEY, $this->b->id);
        $this->get(route('platform.parties.index'))->assertForbidden();
    }

    public function test_admin_string_is_not_a_platform_permission(): void
    {
        $this->get(route('platform.parties.index'))->assertForbidden();
        $this->post(route('platform.parties.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_ambiguous_defaults_require_explicit_choice(): void
    {
        $this->member->companies()->attach($this->b->id, ['is_default' => true]);
        $this->get(route('platform.parties.index'))->assertRedirect(route('platform.company.edit'));
        $this->post(route('platform.parties.store'), $this->payload())->assertStatus(409);
    }

    public function test_no_default_does_not_choose_first_membership(): void
    {
        $this->member->companies()->updateExistingPivot($this->a->id, ['is_default' => false]);
        $this->get(route('platform.parties.index'))->assertRedirect(route('platform.company.edit'));
    }

    public function test_revoked_session_membership_is_rechecked_and_not_replaced_by_default(): void
    {
        $this->member->companies()->attach($this->b->id);
        $this->withSession([CompanyContext::SESSION_KEY => $this->b->id]);
        $this->member->companies()->detach($this->b->id);
        $this->get(route('platform.parties.index'))->assertRedirect(route('platform.company.edit'));
        $this->post(route('platform.parties.store'), $this->payload())->assertStatus(409);
    }

    public function test_inactive_company_cannot_be_selected_or_used(): void
    {
        $this->a->update(['active' => false]);
        $this->post(route('platform.company.update'), ['company_id' => $this->a->id])->assertSessionHasErrors('company_id');
        $this->withSession([CompanyContext::SESSION_KEY => $this->a->id])
            ->get(route('platform.parties.index'))->assertRedirect(route('platform.company.edit'));
    }

    public function test_list_and_search_are_company_scoped(): void
    {
        $this->grant(['view']);
        $mine = $this->party($this->a, 'Visible supplier');
        $other = $this->party($this->b, 'Invisible supplier');
        $this->get(route('platform.parties.index'))->assertOk()->assertSee($mine->legal_name)->assertDontSee($other->legal_name);
        $this->get(route('platform.parties.index', ['q' => 'Invisible']))->assertOk()->assertDontSee($other->legal_name);
    }

    public function test_foreign_id_cannot_be_read_or_updated(): void
    {
        $this->grant(['view', 'update']);
        $other = $this->party($this->b, 'Private supplier');
        $this->get(route('platform.parties.edit', $other->id))->assertNotFound();
        $this->put(route('platform.parties.update', $other->id), $this->payload())->assertNotFound();
        $this->assertSame('Private supplier', $other->fresh()->legal_name);
    }

    public function test_view_permission_cannot_write(): void
    {
        $this->grant(['view']);
        $party = $this->party($this->a);
        $this->post(route('platform.parties.store'), $this->payload())->assertForbidden();
        $this->put(route('platform.parties.update', $party->id), $this->payload())->assertForbidden();
    }

    public function test_create_sets_company_on_server_and_persists_roles_and_audit(): void
    {
        $this->grant(['create']);
        $this->post(route('platform.parties.store'), $this->payload())->assertRedirect(route('platform.company.edit'));
        $party = Party::where('code', 'NEW')->firstOrFail();
        $this->assertEquals($this->a->id, $party->company_id);
        $this->assertTrue($party->hasRole('SUPPLIER'));
        $this->assertDatabaseHas('system_logs', ['user_id' => $this->member->id, 'module' => 'Platform Party', 'action' => 'CREATE']);
    }

    public function test_forged_company_field_is_rejected(): void
    {
        $this->grant(['create']);
        $this->post(route('platform.parties.store'), $this->payload(['company_id' => $this->b->id]))
            ->assertSessionHasErrors('company_id');
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_stale_tab_cannot_write_into_new_company(): void
    {
        $this->grant(['create']);
        $this->member->companies()->attach($this->b->id);
        $this->withSession([CompanyContext::SESSION_KEY => $this->b->id])
            ->post(route('platform.parties.store'), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('parties', 0);
    }

    public function test_code_is_unique_per_company_and_invalid_role_is_rejected(): void
    {
        $this->grant(['create']);
        $this->party($this->b, 'Other', 'NEW');
        $this->post(route('platform.parties.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('platform.parties.store'), $this->payload())->assertSessionHasErrors('code');
        $this->post(route('platform.parties.store'), $this->payload(['code' => 'INVALID', 'roles' => ['ADMIN']]))
            ->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('parties', ['code' => 'INVALID']);
    }

    public function test_update_deactivates_party_and_old_roles_without_deleting_them(): void
    {
        $this->grant(['update']);
        $party = $this->party($this->a);
        $this->put(route('platform.parties.update', $party->id), $this->payload(['code' => $party->code, 'active' => 0, 'roles' => ['CUSTOMER']]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($party->fresh()->active);
        $this->assertDatabaseHas('party_roles', ['party_id' => $party->id, 'role' => 'SUPPLIER', 'active' => false]);
        $this->assertDatabaseHas('party_roles', ['party_id' => $party->id, 'role' => 'CUSTOMER', 'active' => true]);
    }

    public function test_unchecking_all_roles_deactivates_previous_roles(): void
    {
        $this->grant(['update']);
        $party = $this->party($this->a);
        $data = $this->payload(['code' => $party->code]);
        unset($data['roles']);
        $this->put(route('platform.parties.update', $party->id), $data)->assertSessionHasNoErrors();
        $this->assertFalse($party->hasRole('SUPPLIER'));
    }

    public function test_po_and_so_forms_only_list_eligible_parties_in_selected_company(): void
    {
        $supplier = $this->party($this->a, 'LOCAL SUPPLIER');
        $foreign = $this->party($this->b, 'FOREIGN SUPPLIER');
        $inactive = $this->party($this->a, 'INACTIVE SUPPLIER');
        $inactive->update(['active' => false]);
        $customer = $this->party($this->a, 'LOCAL CUSTOMER');
        $customer->roles()->update(['role' => 'CUSTOMER']);
        $this->get(route('po.create'))->assertOk()->assertSee($supplier->legal_name)
            ->assertDontSee($foreign->legal_name)->assertDontSee($inactive->legal_name)->assertDontSee($customer->legal_name);
        $this->get(route('so.create'))->assertOk()->assertSee($customer->legal_name)->assertDontSee($supplier->legal_name);
    }

    public function test_forged_po_and_so_party_submission_creates_no_document(): void
    {
        $foreign = $this->party($this->b);
        $base = ['transaction_date' => '2026-09-24', 'contact_name' => 'Forged', 'party_id' => $foreign->id,
            'details' => [['item_code' => 'TEST-SKU', 'qty' => 1, 'price' => 100]]];
        $this->post(route('po.store'), $base + ['po_number' => 'PO-FORGED'])->assertSessionHasErrors('party_id');
        $this->post(route('so.store'), $base + ['so_number' => 'SO-FORGED', 'receiver_name' => 'Receiver'])->assertSessionHasErrors('party_id');
        $this->assertDatabaseMissing('purchase_orders', ['po_number' => 'PO-FORGED']);
        $this->assertDatabaseMissing('sales_orders', ['so_number' => 'SO-FORGED']);
    }

    public function test_inactive_party_role_is_rejected_by_po_submission(): void
    {
        $party = $this->party($this->a);
        $party->roles()->update(['active' => false]);
        $this->post(route('po.store'), ['po_number' => 'PO-INACTIVE', 'transaction_date' => '2026-09-24',
            'contact_name' => 'Fallback', 'party_id' => $party->id, 'details' => [['item_code' => 'TEST-SKU', 'qty' => 1, 'price' => 100]]])
            ->assertSessionHasErrors('party_id');
        $this->assertDatabaseMissing('purchase_orders', ['po_number' => 'PO-INACTIVE']);
    }

    public function test_permission_catalog_is_idempotent_and_grants_nothing(): void
    {
        $this->seed(PlatformPermissionSeeder::class);
        $this->seed(PlatformPermissionSeeder::class);
        $this->assertSame(3, Permission::where('resource', 'party')->count());
        $this->assertDatabaseCount('role_permission', 0);
    }

    public function test_operator_command_grant_is_specific_and_can_be_removed(): void
    {
        $args = ['user' => $this->member->id, 'company' => $this->a->id, '--allow' => ['view']];
        $this->artisan('platform:party-access', $args)->assertExitCode(0);
        $this->artisan('platform:party-access', $args)->assertExitCode(0);
        $this->assertDatabaseCount('user_role', 1);
        $this->get(route('platform.parties.index'))->assertOk();
        $this->post(route('platform.parties.store'), $this->payload())->assertForbidden();
        $this->artisan('platform:party-access', ['user' => $this->member->id, 'company' => $this->a->id])->assertExitCode(0);
        $this->get(route('platform.parties.index'))->assertForbidden();
    }

    public function test_operator_command_rejects_non_membership_and_unknown_action(): void
    {
        $this->artisan('platform:party-access', ['user' => $this->member->id, 'company' => $this->b->id, '--allow' => ['view']])->assertExitCode(1);
        $this->artisan('platform:party-access', ['user' => $this->member->id, 'company' => $this->a->id, '--allow' => ['delete']])->assertExitCode(1);
        $this->assertDatabaseCount('user_role', 0);
    }

    public function test_revoking_permission_takes_effect_on_next_request(): void
    {
        $this->grant(['view']);
        $this->get(route('platform.parties.index'))->assertOk();
        DB::table('role_permission')->delete();
        $this->get(route('platform.parties.index'))->assertForbidden();
    }

    public function test_party_selection_is_empty_without_company_context(): void
    {
        $party = $this->party($this->a, 'HIDDEN WITHOUT CONTEXT');
        $this->member->companies()->updateExistingPivot($this->a->id, ['is_default' => false]);
        $this->get(route('po.create'))->assertOk()->assertDontSee($party->legal_name);
    }

    public function test_party_validation_does_not_accept_an_arbitrary_roles_string(): void
    {
        $this->grant(['create']);
        $this->post(route('platform.parties.store'), $this->payload(['roles' => 'SUPPLIER']))
            ->assertSessionHasErrors('roles');
        $this->assertDatabaseCount('parties', 0);
    }

    private function grant(array $actions): void
    {
        $role = Role::create(['code' => 'TEST_ROLE_'.Role::count(), 'name' => 'Test role']);
        $role->permissions()->attach(Permission::whereIn('code', array_map(fn ($action) => 'party.'.$action, $actions))->pluck('id'));
        DB::table('user_role')->insert(['user_id' => $this->member->id, 'company_id' => $this->a->id, 'role_id' => $role->id]);
    }

    private function party(Company $company, string $name = 'Test party', ?string $code = null): Party
    {
        $party = Party::create(['company_id' => $company->id, 'code' => $code ?? 'P'.(Party::count() + 1), 'legal_name' => $name, 'active' => true]);
        $party->roles()->create(['role' => 'SUPPLIER', 'active' => true]);

        return $party;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['context_company_id' => $this->a->id, 'code' => 'NEW', 'legal_name' => 'New supplier', 'active' => 1, 'roles' => ['SUPPLIER']], $overrides);
    }
}
