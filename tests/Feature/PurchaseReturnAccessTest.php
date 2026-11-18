<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseReturnAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    private function member(Company $company): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        return $user;
    }

    private function source(Company $company): array
    {
        $po = DB::table('purchase_orders')->insertGetId(['company_id' => $company->id,
            'po_number' => 'PO-ACCESS', 'transaction_date' => '2026-11-05', 'contact_name' => 'Vendor', 'status' => 'RECEIVED']);
        $line = DB::table('purchase_order_details')->insertGetId(['purchase_order_id' => $po,
            'item_code' => 'ACCESS-SKU', 'qty' => 2, 'qty_received' => 2, 'price' => 10, 'amount' => 20]);
        return ['purchase_order_id' => $po, 'return_date' => '2026-11-05', 'items' => [$line => 1]];
    }

    private function assertNoFinancialWrite(): void
    {
        foreach (['purchase_returns', 'purchase_return_details', 'inventory_ledgers', 'journal_headers', 'journal_details'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_role_and_user_grants_are_explicit_and_revocable_without_admin_bypass(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $actor = $this->member($company);
        $other = $this->member($company);
        $actor->update(['role' => 'ADMIN']);
        $payload = $this->source($company);
        $this->actingAs($actor)->get(route('purchase-returns.create'))->assertForbidden();
        $this->post(route('purchase-returns.store'), $payload)->assertForbidden();
        $this->assertNoFinancialWrite();

        $this->artisan('platform:purchase-return-access', ['company' => $company->id, '--role' => 'SUPERADMIN'])->assertExitCode(0);
        $role = Role::where('code', 'SUPERADMIN')->sole();
        $this->assertFalse($actor->platformRoles()->whereKey($role->id)->exists()); // no automatic ADMIN promotion
        $actor->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->get(route('purchase-returns.create'))->assertOk();
        $this->actingAs($other)->get(route('purchase-returns.create'))->assertForbidden();

        $this->artisan('platform:purchase-return-access', ['company' => $company->id, '--user' => $other->id])->assertExitCode(0);
        $this->get(route('purchase-returns.create'))->assertOk();
        $this->artisan('platform:purchase-return-access', ['company' => $company->id, '--user' => $other->id, '--revoke' => true])->assertExitCode(0);
        $this->get(route('purchase-returns.create'))->assertForbidden();
        $this->artisan('platform:purchase-return-access', ['company' => $company->id, '--role' => 'SUPERADMIN', '--revoke' => true])->assertExitCode(0);
        $this->actingAs($actor)->get(route('purchase-returns.create'))->assertForbidden();
        $this->assertNoFinancialWrite();
    }

    public function test_a2_precondition_and_foreign_sources_fail_before_any_financial_write(): void
    {
        config(['platform.order_company_scope_enabled' => false, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $actor = $this->member($company);
        $payload = $this->source($company);
        $this->artisan('platform:purchase-return-access', ['company' => $company->id, '--user' => $actor->id])->assertExitCode(0);
        $this->actingAs($actor)->post(route('purchase-returns.store'), $payload)->assertStatus(409);
        config(['platform.order_company_scope_enabled' => true]);
        $this->post(route('purchase-returns.store'), array_replace($payload, ['purchase_order_id' => 999999]))->assertNotFound();
        $this->post(route('purchase-returns.store'), array_replace($payload, ['items' => [999999 => 1]]))->assertNotFound();
        $this->post(route('purchase-returns.store'), $payload + ['company_id' => $company->id])->assertStatus(422);
        $this->assertNoFinancialWrite();
    }
}