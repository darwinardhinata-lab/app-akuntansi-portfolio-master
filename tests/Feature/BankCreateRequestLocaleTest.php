<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\PlatformPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BankCreateRequestLocaleTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return [
            'Indonesian' => ['id', 'Buat permintaan pembayaran'],
            'English' => ['en', 'Create payment request'],
            'Chinese' => ['zh_CN', '创建付款申请'],
        ];
    }

    #[DataProvider('locales')]
    public function test_requester_sees_localized_create_request_button(string $locale, string $label): void
    {
        config([
            'platform.filament_enabled' => true,
            'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false,
            'payment_plan_v33.enabled' => true,
            'payment_plan_v33.bank_workflow' => true,
        ]);
        $this->seed(PlatformPermissionSeeder::class);

        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $role = Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        $role->permissions()->attach(Permission::where('code', 'erp.panel.access')->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);

        $this->actingAs($user)->withSession(['locale' => $locale])->get('/erp-panel/bank-payments')
            ->assertOk()
            ->assertSee('>'.$label.'</button>', false)
            ->assertDontSee('bank_payment.create');
    }
}