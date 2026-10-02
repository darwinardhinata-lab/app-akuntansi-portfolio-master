<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Imports\FabricImport;
use App\Modules\Manufacturing\Models\Fabric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FabricMasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Account::create(['account_code' => '114003', 'account_name' => 'Bahan baku', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Persediaan']);
    }

    public function test_fabric_import_preserves_a_type_longer_than_the_legacy_fifty_character_limit(): void
    {
        $fabricType = 'Computer heat transfer label (ink with glue coating, printed on lining)';

        $this->assertGreaterThan(50, mb_strlen($fabricType));

        (new FabricImport('UTF-8', '114003'))->collection(new Collection([[
            'H.005010',
            $fabricType,
            'heat transfer label',
            'FINISHED',
            null,
            'Ukuran 1,8 × 1,2 cm, tahan aus, tahan suhu tinggi',
            null,
            'Putih',
            'PCS',
        ]]));

        $this->assertSame($fabricType, Fabric::where('fabric_code', 'H.005010')->value('fabric_type'));
    }

    public function test_fabric_import_exposes_the_selected_csv_input_encoding(): void
    {
        $this->assertSame('GB18030', (new FabricImport('GB18030'))->getCsvSettings()['input_encoding']);
        $this->assertSame('UTF-8', (new FabricImport)->getCsvSettings()['input_encoding']);
    }

    public function test_fabric_import_preserves_mandarin_material_names(): void
    {
        (new FabricImport('UTF-8', '114003'))->collection(new Collection([[
            '5515110000', 'A.001060', 'Polyester Fabric', '1680D 双丝 PU*2 防泼水', 'Polyester Oxford Fabric', '??/Bahan Utama', 'Hitam', 'Lebar 1,5 m', 'meter', '100',
        ]]));

        $this->assertSame('1680D 双丝 PU*2 防泼水', Fabric::where('fabric_code', 'A.001060')->value('material_name'));
    }
}