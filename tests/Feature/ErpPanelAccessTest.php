<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_flag_membership_and_explicit_permission_are_enforced(): void
    {
        config(['platform.filament_enabled' => false]);
        $this->get('/erp-panel')->assertNotFound();
        config(['platform.filament_enabled' => true]);
        $this->get('/erp-panel')->assertUnauthorized();
        $user = User::factory()->create(['role' => 'ADMIN']);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->actingAs($user)->get('/erp-panel')->assertForbidden();
        $permission = Permission::create(['code' => 'erp.panel.access', 'resource' => 'erp.panel', 'action' => 'access']);
        $role = Role::create(['code' => 'ERP_PANEL', 'name' => 'ERP panel']);
        $role->permissions()->attach($permission->id);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->get('/erp-panel')->assertOk();
        $role->permissions()->detach();
        $this->get('/erp-panel')->assertForbidden();
        $role->permissions()->attach($permission->id);
        $user->companies()->detach();
        $this->get('/erp-panel')->assertForbidden();
    }

    public function test_livewire_division_uses_service_audit_and_rechecks_revoked_permission(): void
    {
        config(['platform.filament_enabled' => true]);
        $user = User::factory()->create();
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'DIVISION_EDITOR', 'name' => 'Division editor']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'division.view', 'division.create', 'division.update'])->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user)->withSession([\App\Modules\Platform\Support\CompanyContext::SESSION_KEY => $company->id]);
        // Livewire's unit-test route disables middleware, including StartSession.
        $this->app->rebinding('request', function ($app, $request) {
            if (!$request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('erp'));
        $component = \Livewire\Livewire::test(\App\Filament\Resources\DivisionResource\Pages\CreateDivision::class)->assertOk()
            ->fillForm(['kode_divisi' => 'qa', 'nama_divisi' => 'quality', 'status_aktif' => true])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('master_divisi', ['kode_divisi' => 'QA', 'nama_divisi' => 'QUALITY']);
        $this->assertDatabaseHas('system_logs', ['action' => 'CREATE', 'module' => 'Divisi', 'user_id' => $user->id]);
        $record = \App\Models\MasterDivisi::where('kode_divisi', 'QA')->firstOrFail();
        $this->get(\App\Filament\Resources\DivisionResource::getUrl('view', ['record' => $record]))->assertOk();
        \Livewire\Livewire::test(\App\Filament\Resources\DivisionResource\Pages\EditDivision::class, ['record' => $record->getKey()])
            ->fillForm(['kode_divisi' => 'qa', 'nama_divisi' => 'quality updated', 'status_aktif' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('QUALITY UPDATED', $record->fresh()->nama_divisi);
        $edit = \Livewire\Livewire::test(\App\Filament\Resources\DivisionResource\Pages\EditDivision::class, ['record' => $record->getKey()])
            ->fillForm(['kode_divisi' => 'qa', 'nama_divisi' => 'quality revised', 'status_aktif' => true]);
        $role->permissions()->detach(Permission::where('code', 'division.update')->value('id'));
        $edit->call('save')->assertForbidden();
        $this->assertSame('QUALITY UPDATED', $record->fresh()->nama_divisi);
        config(['platform.filament_enabled' => false]);
        $component->call('create')->assertForbidden();
    }
}