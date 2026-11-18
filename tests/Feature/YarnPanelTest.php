<?php

namespace Tests\Feature;

use App\Filament\Resources\YarnResource;
use App\Filament\Resources\YarnResource\Pages\ListYarns;
use App\Filament\Resources\YarnResource\Pages\ViewYarn;
use App\Models\User;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class YarnPanelTest extends TestCase
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
            $records->push(Yarn::create(['yarn_code' => sprintf('LEGACY-%03d', $n),
                'yarn_type' => 'KNIT', 'yarn_count' => '30.25', 'material_name' => 'Same material', 'color' => $n === 31 ? 'Unique blue' : 'White',
                'specification' => '50x20 mm', 'unit' => 'ROLL', 'meters_per_roll' => '12.75',
                'stock_quantity' => '1.25', 'is_active' => $n !== 31]));
        }
        $before = Yarn::orderBy('id')->get()->toJson();
        $this->get(YarnResource::getUrl())->assertOk();
        $list = Livewire::test(ListYarns::class)->assertOk()
            ->assertCanSeeTableRecords($records->take(25))->assertCanNotSeeTableRecords($records->skip(25));
        $list->call('gotoPage', 2, 'page')->assertCanSeeTableRecords($records->skip(25));
        $list->searchTable('Unique blue')->assertCanSeeTableRecords([$records->last()])
            ->assertCanNotSeeTableRecords([$records->first()]);
        $list->searchTable('')->filterTable('is_active', true)->assertCanNotSeeTableRecords([$records->last()]);
        $this->assertSame('exportFiltered', $list->instance()->getAction('export')->getLivewireClickHandler());
        $list->call('exportFiltered')->assertFileDownloaded('Master_Yarn_Filtered.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'yarn-export-');
        try {
            file_put_contents($path, base64_decode(data_get($list->effects, 'download.content')));
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            // Thirty active records plus heading, not the current 25-record page.
            $this->assertSame(31, $sheet->getHighestDataRow());
            $this->assertSame('LEGACY-001', $sheet->getCell('B2')->getValue());
            $this->assertSame('LEGACY-030', $sheet->getCell('B31')->getValue());
            $this->assertSame('12.75', $sheet->getCell('J2')->getValue());
            $this->assertSame('1.25', $sheet->getCell('K2')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $list->filterTable('is_active', null)->searchTable('Unique blue')->call('exportFiltered')
            ->assertFileDownloaded('Master_Yarn_Filtered.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'yarn-export-');
        try {
            file_put_contents($path, base64_decode(data_get($list->effects, 'download.content')));
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $this->assertSame(2, $workbook->getActiveSheet()->getHighestDataRow());
            $this->assertSame('LEGACY-031', $workbook->getActiveSheet()->getCell('B2')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertFalse(YarnResource::canCreate());
        $this->assertFalse(YarnResource::canEdit($records->first()));
        $this->assertFalse(YarnResource::canDelete($records->first()));
        $this->assertSame($before, Yarn::orderBy('id')->get()->toJson());
        $detail = $records->last();
        $detail->update(['description' => '<script>unsafe()</script> Yarn identity',
            'category' => 'Synthetic yarn category', 'composition' => 'Cotton 80.25%', 'english_name' => 'Exact yarn identity']);
        $before = Yarn::orderBy('id')->get()->toJson();
        $this->get(YarnResource::getUrl('view', ['record' => $detail]))->assertOk()
            ->assertSee('Synthetic yarn category')->assertSee('Cotton 80.25%')->assertSee('30.25')
            ->assertSee('12.75')->assertSee('Exact yarn identity')->assertSee('Yarn identity')
            ->assertDontSee('<script>unsafe()</script>', false);
        $view = Livewire::test(ViewYarn::class, ['record' => $detail->getKey()])->assertOk();
        $this->get(YarnResource::getUrl('view', ['record' => 999999]))->assertNotFound();
        $this->assertSame($before, Yarn::orderBy('id')->get()->toJson());
        $exportList = Livewire::test(ListYarns::class)->assertOk();
        $role->permissions()->detach(Permission::where('code', 'material.view')->value('id'));
        $this->get(YarnResource::getUrl())->assertForbidden();
        $exportList->call('exportFiltered')->assertForbidden();
        $list->searchTable('LEGACY')->assertForbidden();
        $view->call('$refresh')->assertForbidden();
        $this->get(YarnResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $role->permissions()->attach(Permission::where('code', 'material.view')->value('id'));
        $fresh = Livewire::test(ViewYarn::class, ['record' => $detail->getKey()])->assertOk();
        $user->companies()->detach($company->id);
        $fresh->call('$refresh')->assertForbidden();
        $this->get(YarnResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $user->companies()->attach($company->id, ['is_default' => true]);
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'base_currency' => 'IDR', 'active' => true]);
        $this->get(YarnResource::getUrl('view', ['record' => $detail]))->assertForbidden();
        $this->assertSame($before, Yarn::orderBy('id')->get()->toJson());
    }
}
