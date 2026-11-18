<?php

namespace Tests\Feature;

use App\Filament\Resources\FabricResource;
use App\Filament\Resources\FabricResource\Pages\ListFabrics;
use App\Filament\Resources\FabricResource\Pages\ViewFabric;
use App\Models\User;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class FabricPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_is_paginated_searchable_read_only_and_revocation_safe(): void
    {
        config(['platform.filament_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'MATERIAL_READER', 'name' => 'Material reader']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'material.view'])->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user)->withSession([CompanyContext::SESSION_KEY => $company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        Filament::setCurrentPanel(Filament::getPanel('erp'));
        $records = collect();
        for ($n = 1; $n <= 31; $n++) {
            $records->push(Fabric::create(['fabric_code' => sprintf('LEGACY-%03d', $n),
                'fabric_type' => 'KNIT', 'state' => $n === 31 ? 'FINISHED' : 'GREY', 'material_name' => 'Same material', 'color' => $n === 31 ? 'Unique blue' : 'White',
                'specification' => '50x20 mm', 'unit' => 'ROLL', 'meters_per_roll' => '12.75',
                'stock_quantity' => '1.25', 'is_active' => $n !== 31]));
        }
        $before = Fabric::orderBy('id')->get()->toJson();
        $this->get(FabricResource::getUrl())->assertOk();
        $list = Livewire::test(ListFabrics::class)->assertOk()
            ->assertCanSeeTableRecords($records->take(25))->assertCanNotSeeTableRecords($records->skip(25));
        $list->call('gotoPage', 2, 'page')->assertCanSeeTableRecords($records->skip(25));
        $list->searchTable('Unique blue')->assertCanSeeTableRecords([$records->last()])
            ->assertCanNotSeeTableRecords([$records->first()]);
        $list->searchTable('')->filterTable('state', 'GREY')->assertCanNotSeeTableRecords([$records->last()]);
        $list->filterTable('state', null)->filterTable('is_active', true)->assertCanNotSeeTableRecords([$records->last()]);
        $list->filterTable('state', 'GREY')->sortTable('fabric_code', 'desc');
        $this->assertSame('exportFiltered', $list->instance()->getAction('export')->getLivewireClickHandler());
        $this->assertExportMatchesQuery($list, 30);
        $list->searchTable('Unique blue');
        $this->assertExportMatchesQuery($list, 0);
        $list->filterTable('state', 'FINISHED')->filterTable('is_active', false);
        $this->assertExportMatchesQuery($list, 1);
        $this->assertFalse(FabricResource::canCreate());
        $this->assertFalse(FabricResource::canEdit($records->first()));
        $this->assertFalse(FabricResource::canDelete($records->first()));
        $this->assertSame($before, Fabric::orderBy('id')->get()->toJson());
        $detail = $records->last();
        $detail->update(['description' => '<script>unsafe()</script> Fabric identity',
            'category' => 'Synthetic fabric category', 'composition' => 'Cotton 80.25%',
            'english_name' => 'Exact fabric identity', 'subtype' => 'SINGLE-JERSEY', 'width' => '150.25', 'gsm' => 180]);
        $before = Fabric::orderBy('id')->get()->toJson();
        $this->get(FabricResource::getUrl('view', ['record' => $detail]))->assertOk()
            ->assertSee('Synthetic fabric category')->assertSee('Cotton 80.25%')->assertSee('150.25')
            ->assertSee('180')->assertSee('12.75')->assertSee('Exact fabric identity')
            ->assertSee('SINGLE-JERSEY')->assertSee('FINISHED')->assertSee('Fabric identity')
            ->assertDontSee('<script>unsafe()</script>', false);
        $view = Livewire::test(ViewFabric::class, ['record' => $detail->getKey()])->assertOk();
        $this->get(FabricResource::getUrl('view', ['record' => 999999]))->assertNotFound();
        $this->assertSame($before, Fabric::orderBy('id')->get()->toJson());
        $export = Livewire::test(ListFabrics::class)->assertOk();
        $role->permissions()->detach(Permission::where('code', 'material.view')->value('id'));
        $this->get(FabricResource::getUrl())->assertForbidden();
        $export->call('exportFiltered')->assertForbidden();
        $list->searchTable('LEGACY')->assertForbidden();
        $view->call('$refresh')->assertForbidden();
        $this->get(FabricResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $role->permissions()->attach(Permission::where('code', 'material.view')->value('id'));
        $fresh = Livewire::test(ViewFabric::class, ['record' => $detail->getKey()])->assertOk();
        $companyExport = Livewire::test(ListFabrics::class)->assertOk();
        $user->companies()->detach($company->id);
        $companyExport->call('exportFiltered')->assertForbidden();
        $fresh->call('$refresh')->assertForbidden();
        $this->get(FabricResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $user->companies()->attach($company->id, ['is_default' => true]);
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'base_currency' => 'IDR', 'active' => true]);
        $this->get(FabricResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $this->assertSame($before, Fabric::orderBy('id')->get()->toJson());
    }

    private function assertExportMatchesQuery($list, int $count): void
    {
        $export = new \App\Modules\Manufacturing\Exports\FabricExport($list->instance()->getFilteredSortedTableQuery(), lookup: true);
        $expected = $export->query()->get()->map(fn ($row) => $export->map($row))->all();
        $this->assertCount($count, $expected);
        $list->call('exportFiltered')->assertFileDownloaded('Master_Fabric_Filtered.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'fabric-export-');
        try {
            file_put_contents($path, base64_decode(data_get($list->effects, 'download.content')));
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $actual = $book->getActiveSheet()->toArray(null, false, false, false);
            $this->assertSame($export->headings(), array_shift($actual));
            $this->assertSame($expected, $actual);
            $this->assertNotContains('average_cost', $export->headings());
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }
}
