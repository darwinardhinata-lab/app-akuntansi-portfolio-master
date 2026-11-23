<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MaterialRollImportTest extends TestCase
{
    use RefreshDatabase;

    public static function imports(): array
    {
        return ['fabric' => ['fabrics', 'fabric_code'], 'yarn' => ['yarns', 'yarn_code']];
    }

    #[DataProvider('imports')]
    public function test_import_preserves_conversion_and_rolls_back_an_invalid_batch(string $type, string $code): void
    {
        Account::create(['account_code' => '114003', 'account_name' => 'Bahan baku', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Persediaan']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route("mfg.{$type}.import");
        $upload = static fn (string $rows) => [
            'file_excel' => UploadedFile::fake()->createWithContent('materials.csv', "Template\nNote\n\n\n\nHS CODE;KODE BAHAN;DESCRIPTION;NAMA BAHAN;ENGLISH;CATEGORY;COLOR;SPEC;UNIT;METER PER GULUNG\n".$rows),
            'inventory_account_code' => '114003',
        ];

        $this->post($url, $upload("5515110000;EXACT;Material;Material;;Main;Blue;0,125 mm;ROLL;12,75\n"))->assertSessionHas('success');
        $this->assertDatabaseHas("mfg_{$type}", [$code => 'EXACT', 'color' => 'Blue', 'specification' => '0,125 mm', 'meters_per_roll' => 12.75]);

        foreach (['0,125', '0', '-1'] as $invalid) {
            $this->post($url, $upload("5515110000;EXACT;Overwrite;Overwrite;;Main;Red;;ROLL;20\n5515110000;BAD;Bad;Bad;;Main;Red;;ROLL;{$invalid}\n"))
                ->assertSessionHas('error', fn ($error) => str_contains($error, 'Baris 8'));
            $this->assertDatabaseCount("mfg_{$type}", 1);
            $this->assertDatabaseHas("mfg_{$type}", [$code => 'EXACT', 'color' => 'Blue', 'meters_per_roll' => 12.75]);
        }
    }
}