<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReturnActionLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_describes_immediate_stock_and_journal_posting_in_each_locale(): void
    {
        $user = User::factory()->create();
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'SUPERADMIN', 'name' => 'Test superadmin']);
        $role->permissions()->attach(Permission::where('code', 'purchase_return.create')->sole()->id);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);

        foreach (['id' => 'Catat Retur &amp; Posting Stok/Jurnal',
            'en' => 'Record Return &amp; Post Stock/Journal',
            'zh_CN' => '登记退货并过账库存和日记账'] as $locale => $label) {
            $this->actingAs($user)->withSession(['locale' => $locale])
                ->get(route('purchase-returns.create'))
                ->assertOk()
                ->assertSee($label, false)
                ->assertDontSee('Save Return Draft')
                ->assertDontSee('Simpan Draft Retur')
                ->assertDontSee('保存退货草稿');
        }
    }
}