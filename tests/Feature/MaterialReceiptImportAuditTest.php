<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Imports\MaterialReceiptImport;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialReceiptImportAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customs.enabled' => false, 'platform.legacy_sync_enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        foreach (['raw_material_inventory' => '114003', 'auxiliary_material_inventory' => '114004', 'input_vat' => '117008', 'accounts_payable' => '211001'] as $key => $code) {
            Account::create(['account_code' => $code, 'account_name' => $key, 'coa_type' => 'ASSET', 'normal_balance' => $key === 'accounts_payable' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $code, 'active' => true]);
        }
        Supplier::create(['supplier_code' => 'SUP', 'supplier_name' => 'Supplier', 'supplier_type' => 'RAW_MATERIAL']);
        Yarn::create(['yarn_code' => 'YARN', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        AuxiliaryMaterial::create(['material_code' => 'AUX', 'material_name' => 'Auxiliary', 'unit' => 'PCS']);
    }

    private function row(string $ref, string $type = 'YARN', string $code = 'YARN'): array
    {
        return [$ref, '2026-10-08', 'SUP', '', '0', $type, $code, 'Material', '2', $type === 'AUXILIARY' ? 'PCS' : 'KGS', '100', 'LOT'];
    }

    public function test_closed_period_import_is_rejected_before_receipt_stock_or_journal_mutation(): void
    {
        config(['platform.period_lifecycle_preview_enabled' => true]);
        DB::table('accounting_periods')->insert(['month' => '2026-10', 'closed' => true]);
        $import = new MaterialReceiptImport();
        $import->collection(collect([$this->row('CLOSED')]));
        $this->assertSame(0, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('closed', $import->getErrors()[0]);
        $this->assertDatabaseCount('mfg_material_receipts', 0);
        $this->assertDatabaseCount('mfg_material_ledgers', 0);
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertEquals(0, Yarn::first()->stock_quantity);
    }

    public function test_bad_item_rejects_whole_group_but_other_group_can_post(): void
    {
        $import = new MaterialReceiptImport();
        $import->collection(collect([$this->row('BAD'), $this->row('BAD', 'YARN', 'MISSING'), $this->row('GOOD')]));
        $this->assertSame(1, $import->getSuccessCount());
        $this->assertCount(1, $import->getErrors());
        $this->assertStringContainsString('8', $import->getErrors()[0]);
        $this->assertDatabaseCount('mfg_material_receipts', 1);
        $this->assertEquals(2, Yarn::first()->stock_quantity);
    }

    public function test_auxiliary_import_posts_full_mixed_document(): void
    {
        $import = new MaterialReceiptImport();
        $import->collection(collect([$this->row('MIXED'), $this->row('MIXED', 'AUXILIARY', 'AUX')]));
        $this->assertSame([], $import->getErrors());
        $this->assertSame(1, $import->getSuccessCount());
        $this->assertDatabaseCount('mfg_material_receipt_details', 2);
        $this->assertEquals(2, AuxiliaryMaterial::first()->stock_quantity);
    }

    public function test_mismatched_group_header_and_bad_numbers_reject_without_mutation(): void
    {
        foreach ([array_replace($this->row('BAD'), [2 => 'OTHER']), array_replace($this->row('BAD'), [8 => '-2'])] as $bad) {
            $import = new MaterialReceiptImport();
            $import->collection(collect([$this->row('BAD'), $bad]));
            $this->assertSame(0, $import->getSuccessCount());
            $this->assertCount(1, $import->getErrors());
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('mfg_material_receipts', 0);
    }

    public function test_po_link_import_tracks_received_quantity_and_rejects_over_receipt(): void
    {
        $po = \App\Modules\Manufacturing\Models\MaterialPurchaseOrder::create([
            'po_number' => 'MFGPO-IMPORT', 'po_date' => '2026-10-08', 'supplier_id' => Supplier::first()->id,
            'status' => 'APPROVED', 'approval_status' => 'APPROVED',
        ]);
        $detail = $po->details()->create(['item_type' => 'YARN', 'yarn_id' => Yarn::first()->id,
            'item_name' => 'Material', 'qty' => 2, 'unit' => 'KGS', 'rate' => 100, 'amount' => 200, 'qty_received' => 0]);
        $row = $this->row('LINKED') + [12 => $po->po_number, 13 => (string) $detail->id, 14 => '2026-10-07'];
        $import = new MaterialReceiptImport();
        $import->collection(collect([$row]));
        $this->assertSame([], $import->getErrors());
        $this->assertSame(1, $import->getSuccessCount());
        $this->assertEquals(2, $detail->fresh()->qty_received);
        $receipt = \App\Modules\Manufacturing\Models\MaterialReceipt::first();
        $this->assertEquals($po->id, $receipt->po_id);
        $this->assertSame('2026-10-07', $receipt->supplier_doc_date->format('Y-m-d'));
        $retry = new MaterialReceiptImport();
        $retry->collection(collect([array_replace($row, [0 => 'OVER'])]));
        $this->assertSame(0, $retry->getSuccessCount());
        $this->assertCount(1, $retry->getErrors());
        $this->assertDatabaseCount('mfg_material_receipts', 1);
    }

    public function test_real_xlsx_import_starts_at_row_seven(): void
    {
        $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet->getActiveSheet()->fromArray([$this->row('XLSX'), $this->row('XLSX', 'AUXILIARY', 'AUX')], null, 'A7');
        $path = tempnam(sys_get_temp_dir(), 'mrn-audit-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet))->save($path);
            $import = new MaterialReceiptImport();
            \Maatwebsite\Excel\Facades\Excel::import($import, $path, null, \Maatwebsite\Excel\Excel::XLSX);
            $this->assertSame([], $import->getErrors());
            $this->assertSame(1, $import->getSuccessCount());
            $this->assertDatabaseCount('mfg_material_receipt_details', 2);
        } finally {
            unlink($path);
            $sheet->disconnectWorksheets();
        }
    }
}