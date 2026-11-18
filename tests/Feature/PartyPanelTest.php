<?php

namespace Tests\Feature;

use App\Filament\Resources\PartyResource;
use App\Filament\Resources\PartyResource\Pages\ListParties;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class PartyPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_lookup_filters_active_roles_without_writes_and_rechecks_permission(): void
    {
        config(['platform.filament_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'PARTY_READER', 'name' => 'Party reader']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'party.view'])->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user)->withSession([CompanyContext::SESSION_KEY => $company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (! $request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        Filament::setCurrentPanel(Filament::getPanel('erp'));
        $records = collect();
        for ($n = 1; $n <= 31; $n++) {
            $party = Party::create(['company_id' => $company->id, 'code' => sprintf('PARTY-%04d', $n),
                'legal_name' => $n === 31 ? 'Unique supplier' : 'Same legal name',
                'tax_no' => '0000123456789', 'active' => $n !== 31]);
            $party->roles()->create(['role' => $n === 31 ? 'SUPPLIER' : 'CUSTOMER', 'active' => true]);
            $records->push($party);
        }
        $records->first()->roles()->create(['role' => 'SUPPLIER', 'active' => false]);
        $before = [DB::table('parties')->orderBy('id')->get()->toJson(), DB::table('party_roles')->orderBy('id')->get()->toJson()];
        $this->get(PartyResource::getUrl())->assertOk();
        $list = Livewire::test(ListParties::class)->assertOk()
            ->assertCanSeeTableRecords($records->take(25))->assertCanNotSeeTableRecords($records->skip(25));
        $list->call('gotoPage', 2, 'page')->assertCanSeeTableRecords($records->skip(25));
        $list->searchTable('Unique supplier')->assertCanSeeTableRecords([$records->last()])->assertCanNotSeeTableRecords([$records->first()]);
        $list->searchTable('')->filterTable('role', 'SUPPLIER')->assertCanSeeTableRecords([$records->last()])
            ->assertCanNotSeeTableRecords([$records->first()]);
        $list->filterTable('role', null)->filterTable('active', true)->assertCanNotSeeTableRecords([$records->last()]);
        $this->assertFalse(PartyResource::canCreate());
        $this->assertFalse(PartyResource::canEdit($records->first()));
        $this->assertFalse(PartyResource::canDelete($records->first()));
        $this->assertSame($before, [DB::table('parties')->orderBy('id')->get()->toJson(), DB::table('party_roles')->orderBy('id')->get()->toJson()]);
        $role->permissions()->detach(Permission::where('code', 'party.view')->value('id'));
        $this->get(PartyResource::getUrl())->assertForbidden();
        $list->searchTable('PARTY')->assertForbidden();
        $role->permissions()->syncWithoutDetaching(Permission::where('code', 'party.view')->pluck('id'));
        $user->companies()->detach($company->id);
        $this->get(PartyResource::getUrl())->assertForbidden();
        $user->companies()->attach($company->id, ['is_default' => true]);
        // A denied Livewire test response has no next snapshot; load a fresh authorized component.
        $scoped = Livewire::test(ListParties::class)->assertOk();
        $foreign = Company::create(['code' => 'OTHER', 'name' => 'Other company', 'base_currency' => 'IDR', 'active' => true]);
        Party::create(['company_id' => $foreign->id, 'code' => 'PARTY-0001', 'legal_name' => 'Foreign identity', 'active' => true]);
        // Shared ERP panel is still single-MGI only; adding a second company must fail closed.
        $this->get(PartyResource::getUrl())->assertForbidden();
        $scoped->searchTable('Foreign')->assertForbidden();
    }
}