<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Exports\AuxiliaryMaterialExport;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AuxiliaryMaterialExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_and_legacy_exports_preserve_literal_values_and_source_decimals(): void
    {
        $material = AuxiliaryMaterial::create(['material_code' => '000123', 'hs_code' => '00123456',
            'description' => '=1+1', 'material_name' => '+SUM(1,2)', 'english_name' => '@unsafe',
            'category' => '-2+3', 'color' => 'Blue', 'specification' => '50x20 mm', 'unit' => 'ROLL',
            'meters_per_roll' => '12.75', 'stock_quantity' => '123456789012.25', 'average_cost' => '0.01', 'is_active' => true]);
        $before = AuxiliaryMaterial::get()->toJson();
        foreach ([false, true] as $lookup) {
            $export = new AuxiliaryMaterialExport(AuxiliaryMaterial::query(), lookup: $lookup);
            $path = tempnam(sys_get_temp_dir(), 'auxiliary-safe-');
            try {
                file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
                $book = IOFactory::load($path);
                foreach ($export->map($material->fresh()) as $index => $value) {
                    $cell = $book->getActiveSheet()->getCell([$index + 1, 2]);
                    $this->assertSame($value, $cell->getValue());
                    if (is_string($value)) $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
                }
                $this->assertCount($lookup ? 12 : 13, $export->headings());
                $book->disconnectWorksheets();
            } finally {
                unlink($path);
            }
        }
        $this->assertSame($before, AuxiliaryMaterial::get()->toJson());
    }
}