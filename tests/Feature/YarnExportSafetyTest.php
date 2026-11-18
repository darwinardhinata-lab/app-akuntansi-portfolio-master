<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Exports\YarnExport;
use App\Modules\Manufacturing\Models\Yarn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class YarnExportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_keeps_formula_like_text_identifiers_and_decimal_strings_literal(): void
    {
        Yarn::create(['yarn_code' => '000123', 'yarn_type' => 'SYNTHETIC', 'hs_code' => '00123456', 'description' => '=1+1',
            'material_name' => '+SUM(1,2)', 'english_name' => '@unsafe', 'category' => '-2+3',
            'unit' => 'ROLL', 'meters_per_roll' => '12.75', 'stock_quantity' => '123456789012.25',
            'average_cost' => '0.01', 'is_active' => true]);
        $before = Yarn::get()->toJson();
        $path = tempnam(sys_get_temp_dir(), 'yarn-safe-');
        try {
            file_put_contents($path, Excel::raw(new YarnExport(Yarn::query()), \Maatwebsite\Excel\Excel::XLSX));
            $book = IOFactory::load($path);
            $sheet = $book->getActiveSheet();
            foreach (['A2' => '00123456', 'B2' => '000123', 'C2' => '=1+1', 'D2' => '+SUM(1,2)',
                'E2' => '@unsafe', 'F2' => '-2+3', 'J2' => '12.75', 'K2' => '123456789012.25', 'L2' => '0.01'] as $cell => $value) {
                $this->assertSame($value, $sheet->getCell($cell)->getValue());
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType());
            }
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertSame($before, Yarn::get()->toJson());
    }
}