<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralLedger;
use App\Models\Account;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use App\Modules\Platform\Support\PurchaseBillReadAccess;
use App\Support\PostingService;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PurchaseBillReadAccessTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $reader;
    private Role $role;
    private PurchaseBill $bill;
    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false, 'platform.filament_enabled' => true, 'libretranslate.enabled' => false]);
        $this->company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        $this->reader = User::factory()->create();
        $this->reader->companies()->attach($this->company->id, ['is_default' => true]);
        $this->actingAs($this->reader)->withSession([CompanyContext::SESSION_KEY => $this->company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (!$request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        $this->seed(PlatformPermissionSeeder::class);
        $this->role = Role::create(['code' => 'BILL_READER', 'name' => 'Bill reader']);
        $this->role->permissions()->attach(Permission::whereIn('code', ['purchase_bill.view', 'erp.panel.access', 'gl.view'])->pluck('id'));
        $this->reader->platformRoles()->attach($this->role->id, ['company_id' => $this->company->id]);
        $this->po = PurchaseOrder::create(['po_number' => 'PO-OWNED', 'transaction_date' => '2026-10-01', 'status' => 'APPROVED', 'contact_name' => 'Supplier']);
        $this->bill = PurchaseBill::create(['bill_number' => 'BIL-OWNED', 'bill_date' => '2026-10-01', 'grand_total' => '100.25',
            'vendor_name' => 'Scoped supplier', 'purchase_order_id' => $this->po->id]);
        Filament::setCurrentPanel(Filament::getPanel('erp'));
    }

    public function test_owned_detail_is_read_only_and_permission_is_rechecked_at_direct_url(): void
    {
        $before = $this->inventory();
        $url = route('purchase-bills.show', $this->bill->id);
        $this->get($url)->assertOk()->assertSee('BIL-OWNED')->assertSee('PO-OWNED')->assertDontSee('name="_method"', false);
        $this->role->permissions()->detach(Permission::where('code', 'purchase_bill.view')->value('id'));
        $this->get($url)->assertForbidden();
        $this->assertSame($before, $this->inventory());
    }

    public function test_null_po_unowned_po_and_persisted_link_changes_are_denied_without_mutation(): void
    {
        $url = route('purchase-bills.show', $this->bill->id);
        $this->bill->update(['purchase_order_id' => null]);
        $before = $this->inventory();
        $this->getJson($url)->assertNotFound()->assertJsonPath('message', 'Tagihan tidak memiliki bukti kepemilikan PO yang dapat diakses.');
        $this->assertSame($before, $this->inventory());
        $this->bill->update(['purchase_order_id' => $this->po->id]);
        DB::table('purchase_orders')->where('id', $this->po->id)->update(['company_id' => null]);
        $this->get($url)->assertNotFound();
        // A stale model with a valid-looking PO cannot bypass the persisted ownership query.
        $stale = $this->bill->fresh();
        $this->bill->update(['purchase_order_id' => null]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PurchaseBillReadAccess::class)->authorize(request(), $stale);
    }

    public function test_revoked_membership_other_company_and_disabled_ownership_fail_closed(): void
    {
        $url = route('purchase-bills.show', $this->bill->id);
        $this->reader->companies()->detach($this->company->id);
        $this->get($url)->assertStatus(409);
        $this->reader->companies()->attach($this->company->id, ['is_default' => true]);
        config(['platform.order_company_scope_enabled' => false]);
        $this->get($url)->assertStatus(409);
        config(['platform.order_company_scope_enabled' => true]);
        $other = Company::create(['code' => 'OTHER', 'name' => 'Other', 'active' => true]);
        DB::table('purchase_orders')->where('id', $this->po->id)->update(['company_id' => $other->id]);
        $this->get($url)->assertStatus(409); // Existing single-MGI operational contract rejects multiple companies.
    }

    public function test_gl_only_and_ambiguous_application_journal_refs_never_authorize_a_bill(): void
    {
        foreach (['111001' => 'DEBET', '211001' => 'KREDIT'] as $code => $normal) {
            Account::create(['account_code' => (string) $code, 'account_name' => 'Account', 'normal_balance' => $normal, 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
        }
        $journal = PostingService::post(['transaction_date' => '2026-10-01', 'journal_type' => 'MANUAL'], [
            ['account_code' => '111001', 'position' => 'DEBET', 'amount' => '100.25'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '100.25'],
        ]);
        // Synthetic application references are NOT purchasing-posting provenance.
        $this->bill->update(['journal_id' => $journal->journal_id]);
        PurchaseBill::create(['bill_number' => 'BIL-AMBIGUOUS', 'bill_date' => '2026-10-01', 'grand_total' => '100.25',
            'vendor_name' => 'Synthetic supplier', 'purchase_order_id' => $this->po->id, 'journal_id' => $journal->journal_id]);
        $params = ['start' => '2026-10-01', 'end' => '2026-10-31', 'account' => '111001', 'journal' => $journal->journal_id];
        $before = $this->inventory();
        $this->get(GeneralLedger::getUrl($params))->assertOk()->assertSee('100.25')->assertSee(__('gl.unlinked'))
            ->assertDontSee('data-gl-source', false);
        $this->role->permissions()->detach(Permission::where('code', 'purchase_bill.view')->value('id'));
        $this->get(GeneralLedger::getUrl($params))->assertOk()->assertSee(__('gl.unlinked'))->assertDontSee('BIL-OWNED');
        $this->get(route('purchase-bills.show', $this->bill->id))->assertForbidden();
        $this->assertSame($before, $this->inventory());
    }

    private function inventory(): array
    {
        return collect(['purchase_bills', 'purchase_orders', 'journal_headers', 'journal_details', 'inventory_ledgers'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->toJson()])->all();
    }
}