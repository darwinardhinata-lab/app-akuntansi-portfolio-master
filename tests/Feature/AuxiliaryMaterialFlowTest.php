<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalHeader;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\AuxiliaryMaterialIssueService;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Modules\Manufacturing\Imports\AuxiliaryMaterialImport;
use Illuminate\Support\Collection;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuxiliaryMaterialFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_auxiliary_material_mrn_wip_and_expense_issue_with_append_only_reversal(): void
    {
        $this->seedCoa();
        $material = AuxiliaryMaterial::create(['material_code' => 'AUX-LABEL', 'material_name' => 'Label Woven', 'category' => 'Label', 'unit' => 'PCS', 'inventory_account_code' => '114004', 'is_active' => true]);
        $supplier = Supplier::create(['supplier_code' => 'SUP-AUX', 'supplier_name' => 'Supplier Auxiliary', 'supplier_type' => 'RAW_MATERIAL', 'is_active' => true]);

        $receipt = app(MaterialReceiptService::class)->createAndPost(['receipt_date' => '2026-09-30', 'supplier_id' => $supplier->id], [[
            'item_type' => 'AUXILIARY', 'auxiliary_material_id' => $material->id, 'item_name' => $material->material_name, 'qty' => 10, 'unit' => 'PCS', 'rate' => 100,
        ]]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->journal_id, 'account_code' => '114004', 'position' => 'DEBET', 'amount' => 1000]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->journal_id, 'account_code' => '211001', 'position' => 'KREDIT', 'amount' => 1000]);
        $this->assertSame(10.0, (float) $material->fresh()->stock_quantity);

        $line = ProductionLine::create(['line_code' => 'AUX-LINE', 'line_name' => 'Auxiliary Line', 'is_active' => true]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-AUX', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Aux Test', 'planned_qty' => 10]);
        $service = app(AuxiliaryMaterialIssueService::class);
        $wipIssue = $service->issue(['issue_date' => '2026-09-30', 'auxiliary_material_id' => $material->id, 'work_order_id' => $workOrder->id, 'line_id' => $line->id, 'usage_type' => 'WIP', 'qty' => 4]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $wipIssue->journal_id, 'account_code' => '114002', 'position' => 'DEBET', 'amount' => 400]);
        $this->assertEquals(400, $workOrder->fresh()->total_wip_cost);

        $expenseIssue = $service->issue(['issue_date' => '2026-09-30', 'auxiliary_material_id' => $material->id, 'usage_type' => 'EXPENSE', 'qty' => 2]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $expenseIssue->journal_id, 'account_code' => '510010', 'position' => 'DEBET', 'amount' => 200]);
        $this->assertSame(4.0, (float) $material->fresh()->stock_quantity);

        $service->void($wipIssue->id);
        $wipIssue->refresh();
        $this->assertNotNull($wipIssue->voided_at);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $wipIssue->journal_id, 'transaction_type' => 'Auxiliary Material Issue (MFG)']);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $wipIssue->reversal_journal_id, 'account_code' => '114004', 'position' => 'DEBET', 'amount' => 400]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $wipIssue->reversal_journal_id, 'account_code' => '114002', 'position' => 'KREDIT', 'amount' => 400]);
        $this->assertEquals(0, $workOrder->fresh()->total_wip_cost);
    }

    public function test_auxiliary_master_import_updates_metadata_without_changing_stock_or_average_cost(): void
    {
        $material = AuxiliaryMaterial::create(['material_code' => 'AUX-IMPORT', 'material_name' => 'Old Name', 'unit' => 'PCS', 'stock_quantity' => 12, 'average_cost' => 345, 'inventory_account_code' => '114004', 'is_active' => false]);

        app(AuxiliaryMaterialImport::class)->collection(new Collection([['AUX-IMPORT', 'New Name', 'Packaging', '20x30', 'PCS']]));

        $material->refresh();
        $this->assertSame('New Name', $material->material_name);
        $this->assertSame(12.0, (float) $material->stock_quantity);
        $this->assertSame(345.0, (float) $material->average_cost);
        $this->assertTrue($material->is_active);
    }

    private function seedCoa(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        foreach ([['114002', 'WIP', 'DEBET'], ['114004', 'Bahan Penolong', 'DEBET'], ['117008', 'PPN Masukan', 'DEBET'], ['211001', 'Utang Usaha', 'KREDIT'], ['510010', 'Beban Bahan Penolong', 'DEBET']] as [$code, $name, $balance]) {
            Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => 'Test', 'normal_balance' => $balance, 'report_pos' => 'NERACA']);
        }
        foreach (['wip_inventory' => '114002', 'auxiliary_material_inventory' => '114004', 'input_vat' => '117008', 'accounts_payable' => '211001', 'auxiliary_material_expense' => '510010'] as $key => $account) {
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $account, 'active' => true]);
        }
    }
}