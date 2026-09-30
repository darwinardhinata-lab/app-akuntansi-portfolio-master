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

    public function test_fabric_import_preserves_a_type_longer_than_the_legacy_fifty_character_limit(): void
    {
        $fabricType = 'Computer heat transfer label (ink with glue coating, printed on lining)';

        $this->assertGreaterThan(50, mb_strlen($fabricType));

        app(FabricImport::class)->collection(new Collection([[
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
}