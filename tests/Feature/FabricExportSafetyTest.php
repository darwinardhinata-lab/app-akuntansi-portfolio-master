<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Exports\FabricExport;
use App\Modules\Manufacturing\Models\Fabric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FabricExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_preserves_permitted_fields_and_legacy_export_contract_remains_compatible(): void
    {
        $fabric = Fabric::create(['fabric_code' => '000123', 'fabric_type' => 'KNIT', 'state' => 'GREY',
            'hs_code' => '00123456', 'description' => '=1+1', 'material_name' => '+SUM(1,2)',
            'english_name' => '@unsafe', 'category' => '-2+3', 'color' => 'Blue', 'specification' => '50x20 mm',
            'composition' => 'Cotton 80.25%', 'subtype' => 'JERSEY', 'width' => '150.25', 'gsm' => 180,
            'unit' => 'ROLL', 'meters_per_roll' => '12.75', 'stock_quantity' => '123456789012.25',
            'average_cost' => '0.01', 'is_active' => true]);
        $before = Fabric::get()->toJson();
        foreach ([false, true] as $lookup) {
            $export = new FabricExport(Fabric::query(), lookup: $lookup);
            $path = tempnam(sys_get_temp_dir(), 'fabric-safe-');
            try {
                file_put_contents($path, Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX));
                $book = IOFactory::load($path);
                $sheet = $book->getActiveSheet();
                $expected = $export->map($fabric->fresh());
                foreach ($expected as $index => $value) {
                    $cell = $sheet->getCell([$index + 1, 2]);
                    $this->assertSame($value, $cell->getValue());
                    if (is_string($value)) $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
                }
                $this->assertCount($lookup ? 19 : 13, $export->headings());
                $book->disconnectWorksheets();
            } finally {
                unlink($path);
            }
        }
        $this->assertSame('150.25', $fabric->fresh()->width);
        $this->assertSame($before, Fabric::get()->toJson());
    }
}