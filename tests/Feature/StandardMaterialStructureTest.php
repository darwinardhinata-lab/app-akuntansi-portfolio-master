<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Imports\AuxiliaryMaterialImport;
use App\Modules\Manufacturing\Imports\FabricImport;
use App\Modules\Manufacturing\Imports\YarnImport;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandardMaterialStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Account::create(['account_code' => '114003', 'account_name' => 'Bahan baku', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Persediaan']);
    }

    public function test_standard_material_columns_are_imported_for_yarn_fabric_and_auxiliary_material(): void
    {
        $row = ['5515110000', 'MAT-001', 'Polyester Fabric', 'Kain Polyester', 'Polyester Oxford Fabric', 'Bahan Utama', 'Hitam', 'Lebar 1,5 m; tahan air', 'METER', '100'];

        app(YarnImport::class)->collection(new Collection([$row]));
        (new FabricImport('UTF-8', '114003'))->collection(new Collection([$row]));
        app(AuxiliaryMaterialImport::class)->collection(new Collection([$row]));

        foreach ([Yarn::class => 'yarn_code', Fabric::class => 'fabric_code', AuxiliaryMaterial::class => 'material_code'] as $model => $codeColumn) {
            $material = $model::where($codeColumn, 'MAT-001')->firstOrFail();
            $this->assertSame('5515110000', $material->hs_code);
            $this->assertSame('Polyester Fabric', $material->description);
            $this->assertSame('Kain Polyester', $material->material_name);
            $this->assertSame('Polyester Oxford Fabric', $material->english_name);
            $this->assertSame('Bahan Utama', $material->category);
            $this->assertSame('Hitam', $material->color);
            $this->assertSame('Lebar 1,5 m; tahan air', $material->specification);
            $this->assertSame('METER', $material->unit);
            $this->assertSame(100.0, (float) $material->meters_per_roll);
        }

        $this->assertSame('Polyester Fabric', Yarn::where('yarn_code', 'MAT-001')->value('yarn_type'));
        $this->assertSame('Polyester Fabric', Fabric::where('fabric_code', 'MAT-001')->value('fabric_type'));
        $this->assertFalse(Schema::hasColumn('mfg_fabrics', 'itinvent_name'));
        $this->assertFalse(Schema::hasColumn('mfg_yarns', 'itinvent_name'));
        $this->assertFalse(Schema::hasColumn('mfg_auxiliary_materials', 'itinvent_name'));
    }
}