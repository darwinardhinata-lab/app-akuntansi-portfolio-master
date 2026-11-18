<?php

namespace Tests\Feature;

use App\Filament\Resources\AuxiliaryMaterialResource\Pages\PreviewAuxiliaryMaterialImport;
use App\Http\Middleware\RejectPanelUploads;
use App\Models\User;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Imports\AuxiliaryMaterialPreview;
use App\Modules\Manufacturing\Support\AuxiliaryMaterialPreviewUploadPolicy;
use App\Modules\PaymentPlan\Services\PrivateAttachmentStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\CompanyContext;
use Database\Seeders\PlatformPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

final class AuxiliaryMaterialImportPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function reader(): array
    {
        config(['platform.filament_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'PREVIEW_READER', 'name' => 'Preview reader']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access', 'material.view'])->pluck('id'));
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user)->withSession([CompanyContext::SESSION_KEY => $company->id]);
        $this->app->rebinding('request', function ($app, $request) {
            if (! $request->hasSession()) $request->setLaravelSession($app['session']->driver());
        });
        Filament::setCurrentPanel(Filament::getPanel('erp'));
        return [$company, $user, $role];
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        foreach (array_merge([['TEMPLATE IMPORT MASTER BAHAN PENOLONG'], ['Stok dan HPP tidak diimport'], [], [], [],
            ['HS CODE', 'KODE BAHAN', 'DESCRIPTION', 'NAMA BAHAN', 'NAMA MATERIAL INGGRIS', 'KATEGORI', 'WARNA', 'SPESIFIKASI/DESKRIPSI', 'SATUAN', 'METER PER GULUNG']], $rows) as $row) fputcsv($stream, $row, ';');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    private function xlsx(array $rows): string
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $headings = ['HS CODE', 'KODE BAHAN', 'DESCRIPTION', 'NAMA BAHAN', 'NAMA MATERIAL INGGRIS',
            'KATEGORI', 'WARNA', 'SPESIFIKASI/DESKRIPSI', 'SATUAN', 'METER PER GULUNG'];
        $ws->setCellValue('A1', 'TEMPLATE IMPORT MASTER BAHAN PENOLONG');
        $ws->setCellValue('A2', 'Stok dan HPP tidak diimport');
        foreach ($headings as $idx => $heading) {
            $col = Coordinate::stringFromColumnIndex($idx + 1);
            $ws->setCellValueExplicit($col . '6', $heading, DataType::TYPE_STRING);
        }
        foreach ($rows as $rIdx => $row) {
            $rowNum = $rIdx + 7;
            foreach ($row as $cIdx => $val) {
                $col = Coordinate::stringFromColumnIndex($cIdx + 1);
                $ws->setCellValueExplicit($col . $rowNum, (string) $val, DataType::TYPE_STRING);
            }
        }
        $tempPath = tempnam(sys_get_temp_dir(), 'mfg_aux_') . '.xlsx';
        $writer = new XlsxWriter($ss);
        $writer->save($tempPath);
        $ss->disconnectWorksheets();
        unset($ss);
        return $tempPath;
    }

    private function inventory(): array
    {
        $result = [];
        foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $table) {
            $result[$table->name] = DB::table($table->name)->get()->toJson();
        }
        return $result;
    }

    public function test_normal_preview_reports_rows_duplicates_and_unmapped_without_writes(): void
    {
        $this->reader();
        $permission = Permission::where('code', 'material.create')->sole();
        $this->assertSame('create', $permission->action);
        $this->assertSame(0, $permission->roles()->count(), 'Catalog seeding must not grant write access.');
        AuxiliaryMaterial::create(['material_code' => '0001', 'material_name' => 'Existing', 'unit' => 'PCS', 'stock_quantity' => '123.45', 'average_cost' => '67.89']);
        $before = $this->inventory();
        $this->get(PreviewAuxiliaryMaterialImport::getUrl())->assertOk()->assertSee('CSV');
        $csv = $this->csv([
            ['00123', '0001', '=1+1', '<script>alert(1)</script>', '+name', 'Category', 'Blue', "multi\nline;spec", 'PCS', '12.75'],
            ['', '0002', '', 'New', '', '', '', '', 'PCS', ''],
            ['', '0002', '', 'Duplicate', '', '', '', '', 'PCS', ''],
            ['', 'BAD', '', '', '', '', '', '', '', '-2'],
            ['', 'EXTRA', '', 'Name', '', '', '', '', 'PCS', '', 'unmapped'],
        ]);
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)->set('csv', $csv)->call('preview')->assertHasNoErrors();
        $rows = $page->get('rows');
        $this->assertCount(5, $rows);
        $this->assertSame(7, $rows[0]['row']);
        $this->assertSame('0001', $rows[0]['data']['material_code']);
        $this->assertSame('12.75', $rows[0]['data']['meters_per_roll']);
        $this->assertTrue($rows[0]['existing_duplicate']);
        $this->assertTrue($rows[1]['file_duplicate']);
        $this->assertTrue($rows[2]['file_duplicate']);
        $this->assertNotEmpty($rows[3]['errors']);
        $this->assertSame(['column_11' => 'unmapped'], $rows[4]['unmapped']);
        $page->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame($before, $this->inventory());
    }

    public function test_template_mismatch_and_revoked_access_are_denied_without_writes(): void
    {
        [$company, $user, $role] = $this->reader();
        $before = $this->inventory();
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)->set('csv', 'unknown;format')->call('preview')->assertHasErrors('csv')->assertSet('rows', []);
        $this->assertSame($before, $this->inventory());
        $role->permissions()->detach(Permission::where('code', 'material.view')->value('id'));
        $page->call('preview')->assertForbidden();
        $this->get(PreviewAuxiliaryMaterialImport::getUrl())->assertForbidden();
        $role->permissions()->attach(Permission::where('code', 'material.view')->value('id'));
        $fresh = Livewire::test(PreviewAuxiliaryMaterialImport::class);
        $user->companies()->detach($company->id);
        $fresh->call('preview')->assertForbidden();
    }

    public function test_empty_template_short_rows_input_budget_and_multiple_company_gate(): void
    {
        $this->reader();
        $before = $this->inventory();
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)->set('csv', $this->csv([]))->call('preview')
            ->assertHasNoErrors()->assertSet('rows', [])->assertSet('inspected', true);
        $page->set('csv', $this->csv([['00123', 'SHORT', 'Description']]))->call('preview')->assertHasNoErrors();
        $this->assertNotEmpty($page->get('rows')[0]['errors']);
        $page->set('csv', str_repeat('x', 1048577))->call('preview')->assertHasErrors('csv')->assertSet('rows', [])->assertSet('inspected', false);
        $this->assertSame($before, $this->inventory());
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'base_currency' => 'IDR', 'active' => true]);
        $page->call('preview')->assertForbidden();
        $this->get(PreviewAuxiliaryMaterialImport::getUrl())->assertForbidden();
    }

    public function test_file_preview_with_xlsx_and_csv_reports_exact_values_and_preserves_database(): void
    {
        $this->reader();
        AuxiliaryMaterial::create(['material_code' => 'AUX-01', 'material_name' => 'Existing Aux', 'unit' => 'MTR', 'stock_quantity' => '50.00', 'average_cost' => '100.00']);
        $before = $this->inventory();

        // 1. XLSX file preview
        $xlsxPath = $this->xlsx([
            ['580710', 'AUX-01', 'Woven Label', 'Label Tenun', 'Woven Label', 'Bahan Penolong', 'Putih', '50x20 mm', 'MTR', '15.5'],
            ['', 'AUX-02', 'Care Label', 'Label Cuci', '', 'Bahan Penolong', 'Hitam', '', 'PCS', ''],
            ['', 'AUX-02', 'Duplicate Label', 'Label Cuci 2', '', '', '', '', 'PCS', ''],
            ['', 'BAD-CODE', '', '', '', '', '', '', '', '-5'],
            ['', 'EXTRA-COL', '', 'With Extra', '', '', '', '', 'PCS', '10', 'extra_value'],
        ]);

        $uploadedXlsx = UploadedFile::fake()->createWithContent('aux_template.xlsx', file_get_contents($xlsxPath));
        @unlink($xlsxPath);

        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)
            ->set('file', $uploadedXlsx)
            ->call('preview')
            ->assertHasNoErrors();

        $rows = $page->get('rows');
        $this->assertCount(5, $rows);
        $this->assertSame(7, $rows[0]['row']);
        $this->assertSame('AUX-01', $rows[0]['data']['material_code']);
        $this->assertSame('15.5', $rows[0]['data']['meters_per_roll']);
        $this->assertTrue($rows[0]['existing_duplicate']);
        $this->assertFalse($rows[0]['file_duplicate']);
        $this->assertTrue($rows[1]['file_duplicate']);
        $this->assertTrue($rows[2]['file_duplicate']);
        $this->assertNotEmpty($rows[3]['errors']);
        $this->assertSame(['column_11' => 'extra_value'], $rows[4]['unmapped']);
        $this->assertSame('file', $page->get('sourceType'));
        $this->assertSame($before, $this->inventory());

        // 2. CSV file upload preview
        $csvContent = $this->csv([
            ['580720', 'AUX-FILE-01', 'Zipper', 'Ritsleting', 'Zipper', 'Bahan Penolong', 'Navy', 'No 5', 'PCS', '100'],
        ]);
        $uploadedCsv = UploadedFile::fake()->createWithContent('aux_template.csv', $csvContent);

        $pageCsv = Livewire::test(PreviewAuxiliaryMaterialImport::class)
            ->set('file', $uploadedCsv)
            ->call('preview')
            ->assertHasNoErrors();

        $csvRows = $pageCsv->get('rows');
        $this->assertCount(1, $csvRows);
        $this->assertSame('AUX-FILE-01', $csvRows[0]['data']['material_code']);
        $this->assertSame('100', $csvRows[0]['data']['meters_per_roll']);
        $this->assertFalse($csvRows[0]['existing_duplicate']);
        $this->assertFalse($csvRows[0]['file_duplicate']);
        $this->assertSame('file', $pageCsv->get('sourceType'));
        $this->assertSame($before, $this->inventory());
    }

    public function test_create_only_save_requires_current_grant_and_a_preview_of_the_same_file(): void
    {
        [, , $role] = $this->reader();
        $create = Permission::where('code', 'material.create')->sole();
        $upload = fn (string $code) => UploadedFile::fake()->createWithContent('aux.csv', $this->csv([
            ['', $code, '', 'Label', '', '', '', '', 'PCS', '12.750'],
        ]));

        Livewire::test(PreviewAuxiliaryMaterialImport::class)->set('file', $upload('00098'))
            ->call('preview')->call('save')->assertForbidden();
        $role->permissions()->attach($create);
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)->set('file', $upload('00098'))
            ->call('preview')->assertHasNoErrors()->assertSee('Simpan');
        $page->set('file', $upload('00099'))->call('save')->assertHasErrors('save');
        $this->assertDatabaseCount('mfg_auxiliary_materials', 0);
        $page->call('preview')->assertHasNoErrors()->call('save')->assertHasNoErrors()->assertSet('saved', 1);
        $master = AuxiliaryMaterial::sole();
        $this->assertSame(['00099', 'PCS', '12.75', '0.00', '0.00', '114004'], [
            $master->material_code, $master->unit, $master->meters_per_roll, $master->stock_quantity,
            $master->average_cost, $master->inventory_account_code,
        ]);
        $page->call('save')->assertHasErrors('save');
        $this->assertDatabaseCount('mfg_auxiliary_materials', 1);
    }

    public function test_upload_policy_allows_material_preview_readers_but_denies_unauthorized_and_keeps_reconciliation_isolated(): void
    {
        [$company, $user, $role] = $this->reader();
        $policy = app(AuxiliaryMaterialPreviewUploadPolicy::class);
        $request = Request::create('/livewire/upload-file', 'POST');
        $request->setUserResolver(fn () => $user);
        $session = app('session')->driver();
        $session->put(CompanyContext::SESSION_KEY, $company->id);
        $request->setLaravelSession($session);

        // 1. Material reader passes the scoped material preview upload policy
        $this->assertTrue($policy->allows($request, $user));

        // 2. Material reader has NO reconciliation attachment rights
        $reconciliationDenied = false;
        try {
            app(PrivateAttachmentStore::class)->reconciliationCompany('propose');
        } catch (\Throwable) {
            $reconciliationDenied = true;
        }
        $this->assertTrue($reconciliationDenied, 'Material reader must NOT possess reconciliation proposal rights');

        // 3. RejectPanelUploads middleware passes for material preview reader
        $middleware = new RejectPanelUploads();
        $passed = false;
        $response = $middleware->handle($request, function ($req) use (&$passed) {
            $passed = true;
            return response('OK');
        });
        $this->assertTrue($passed);
        $this->assertSame(200, $response->getStatusCode());

        // 4. Revoking material.view causes both policy and RejectPanelUploads to reject
        $role->permissions()->detach(Permission::where('code', 'material.view')->value('id'));
        $this->assertFalse($policy->allows($request, $user));

        $blocked = false;
        try {
            $middleware->handle($request, function ($req) {});
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $blocked = ($e->getStatusCode() === 403);
        }
        $this->assertTrue($blocked, 'RejectPanelUploads must return 403 when permissions are revoked');
    }

    public function test_file_size_budget_and_invalid_extension_are_rejected_without_writes(): void
    {
        $this->reader();
        $before = $this->inventory();

        // Invalid extension (.php)
        $invalidExtFile = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');
        Livewire::test(PreviewAuxiliaryMaterialImport::class)
            ->set('file', $invalidExtFile)
            ->call('preview')
            ->assertHasErrors('file');

        // Too large file (> 5 MB)
        $largeFile = UploadedFile::fake()->create('large.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        Livewire::test(PreviewAuxiliaryMaterialImport::class)
            ->set('file', $largeFile)
            ->call('preview')
            ->assertHasErrors('file');

        $this->assertSame($before, $this->inventory());
    }

    public function test_corrupt_spreadsheet_clears_previous_preview_and_returns_validation_without_writes(): void
    {
        $this->reader();
        $before = $this->inventory();
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class)
            ->set('csv', $this->csv([['', 'VALID', '', 'Valid material', '', '', '', '', 'PCS', '']]))
            ->call('preview')->assertHasNoErrors()->assertSet('inspected', true);

        // ZIP MIME passes the upload validator, but a ZIP without workbook parts is not XLSX.
        $path = tempnam(sys_get_temp_dir(), 'aux-invalid-');
        try {
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path, \ZipArchive::OVERWRITE));
            $zip->addFromString('not-a-workbook.txt', 'invalid workbook');
            $zip->close();
            $file = UploadedFile::fake()->createWithContent('corrupt.xlsx', file_get_contents($path));
            $page->set('file', $file)->call('preview')->assertHasErrors('file')
                ->assertSet('rows', [])->assertSet('inspected', false)->assertSet('sourceType', '');
        } finally {
            @unlink($path);
        }
        $this->assertSame($before, $this->inventory());
    }

    public function test_csv_resource_boundaries_are_exact_and_rejection_clears_preview(): void
    {
        $this->reader();
        $before = $this->inventory();
        $row = ['00123', 'NEW', '=1+1', 'Name', '', '', '', 'text', 'PCS', '123456789012.123456'];
        $page = Livewire::test(PreviewAuxiliaryMaterialImport::class);
        foreach ([
            [array_fill(0, AuxiliaryMaterialPreview::MAX_ROWS, $row), array_fill(0, AuxiliaryMaterialPreview::MAX_ROWS + 1, $row)],
            [[array_pad($row, 20, 'extra')], [array_pad($row, 21, 'extra')]],
            [[array_replace($row, [7 => str_repeat('あ', 4096)])], [array_replace($row, [7 => str_repeat('あ', 4097)])]],
        ] as [$accepted, $rejected]) {
            $page->set('csv', $this->csv($accepted))->call('preview')->assertHasNoErrors()->assertSet('inspected', true);
            $page->set('csv', $this->csv($rejected))->call('preview')->assertHasErrors('csv')
                ->assertSet('rows', [])->assertSet('inspected', false)->assertSet('sourceType', '');
        }
        $page->set('csv', $this->csv([$row]))->call('preview')->assertHasNoErrors();
        $this->assertSame('123456789012.123456', $page->get('rows')[0]['data']['meters_per_roll']);
        $this->assertSame('00123', $page->get('rows')[0]['data']['hs_code']);
        $page->set('file', UploadedFile::fake()->createWithContent('legacy.txt', $this->csv([$row])))
            ->assertSet('rows', [])->call('preview')->assertHasErrors('file');
        $this->assertSame($before, $this->inventory());
    }

    public function test_xlsx_preflight_rejects_archive_expansion_sparse_dimensions_and_multiple_sheets(): void
    {
        $this->reader();
        $before = $this->inventory();
        $path = $this->xlsx([['00123', 'NEW', '=1+1', 'Name', '', '', '', 'text', 'PCS', '12.75']]);
        $original = file_get_contents($path);
        try {
            foreach (['archive', 'dimension', 'sheets', 'dtd'] as $case) {
                file_put_contents($path, $original);
                $zip = new \ZipArchive();
                $this->assertTrue($zip->open($path));
                if ($case === 'archive') $zip->addFromString('padding.bin', str_repeat('x', AuxiliaryMaterialPreview::MAX_ARCHIVE_BYTES));
                if ($case === 'dimension') {
                    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    $zip->addFromString('xl/worksheets/sheet1.xml', preg_replace('/<dimension[^>]*\/>/', '<dimension ref="A1:XFD1048576"/>', $xml));
                }
                if ($case === 'sheets') $zip->addFromString('xl/worksheets/sheet2.xml', $zip->getFromName('xl/worksheets/sheet1.xml'));
                if ($case === 'dtd') $zip->addFromString('padding.xml', '<!DOCTYPE root [<!ENTITY test "unsafe">]><root>&test;</root>');
                $zip->close();
                Livewire::test(PreviewAuxiliaryMaterialImport::class)
                    ->set('file', UploadedFile::fake()->createWithContent('template.xlsx', file_get_contents($path)))
                    ->call('preview')->assertHasErrors('file')->assertSet('rows', [])->assertSet('inspected', false);
            }
        } finally {
            @unlink($path);
        }
        $this->assertSame($before, $this->inventory());
    }
}
