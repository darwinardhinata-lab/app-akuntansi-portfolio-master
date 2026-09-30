<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Models\CuttingOrder;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\CuttingOrderService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuttingScrapWastageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutting_qc_posts_valuable_scrap_and_wastage_to_their_approved_accounts(): void
    {
        $this->seedCoa();
        $line = ProductionLine::create(['line_code' => 'CUT-SCRAP', 'line_name' => 'Cutting Scrap', 'is_active' => true]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-SCRAP', 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 10, 'average_cost' => 100]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-SCRAP', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Scrap Test', 'planned_qty' => 10]);
        $cutting = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10]);

        $check = app(CuttingOrderService::class)->recordCheck($cutting->id, [
            'check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 8, 'pieces_rejected' => 2,
            'fabric_wastage_kg' => 1, 'scrap_kg' => 1, 'scrap_unit_value' => 25,
        ]);

        $this->assertSame(100.0, (float) $check->wastage_cost_amount);
        $this->assertSame(25.0, (float) $check->scrap_value_amount);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $check->journal_id, 'account_code' => '510004', 'position' => 'DEBET', 'amount' => 100]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $check->journal_id, 'account_code' => '114005', 'position' => 'DEBET', 'amount' => 25]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $check->journal_id, 'account_code' => '114002', 'position' => 'KREDIT', 'amount' => 125]);
        $this->assertEquals(375, $workOrder->fresh()->total_wip_cost);
    }

    public function test_cutting_qc_rejects_overallocated_loss_and_second_posting(): void
    {
        $this->seedCoa();
        $line = ProductionLine::create(['line_code' => 'CUT-GUARD', 'line_name' => 'Cutting Guard', 'is_active' => true]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-GUARD', 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 10, 'average_cost' => 100]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-GUARD', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Guard Test', 'planned_qty' => 10]);
        $cutting = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10]);

        try {
            app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10, 'fabric_wastage_kg' => 4, 'scrap_kg' => 2, 'scrap_unit_value' => 25]);
            $this->fail('QC dengan total loss melebihi kain issued harus ditolak.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('melebihi kain', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('mfg_cutting_checks')->count());

        app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10]);
        try {
            app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10]);
            $this->fail('QC kedua harus ditolak.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('sudah diposting', $exception->getMessage());
        }
    }

    public function test_void_qc_keeps_source_journal_and_posts_a_reversal(): void
    {
        $this->seedCoa();
        $line = ProductionLine::create(['line_code' => 'CUT-VOID', 'line_name' => 'Cutting Void', 'is_active' => true]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-VOID', 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 10, 'average_cost' => 100]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-VOID', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Void Test', 'planned_qty' => 10]);
        $cutting = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10]);
        $check = app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 8, 'fabric_wastage_kg' => 1, 'scrap_kg' => 1, 'scrap_unit_value' => 25]);

        app(CuttingOrderService::class)->voidCheck($check->id);

        $check->refresh();
        $this->assertNotNull($check->voided_at);
        $this->assertNotNull($check->reversal_journal_id);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $check->journal_id, 'transaction_type' => 'Cutting QC Scrap/Wastage (MFG)']);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $check->reversal_journal_id, 'account_code' => '114002', 'position' => 'DEBET', 'amount' => 100]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $check->reversal_journal_id, 'account_code' => '114005', 'position' => 'KREDIT', 'amount' => 25]);
        $this->assertSame('OPEN', $cutting->fresh()->status);

        app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10]);
        $this->assertSame(2, DB::table('mfg_cutting_checks')->where('cutting_order_id', $cutting->id)->count());
    }

    private function seedCoa(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        foreach ([['114002', 'Barang dalam proses', 'ASSET'], ['114003', 'Bahan baku', 'ASSET'], ['114005', 'Scrap-Afal', 'ASSET'], ['510004', 'Penyesuaian Persediaan', 'COGS']] as [$code, $name, $type]) {
            Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => $type, 'normal_balance' => 'DEBET', 'report_pos' => $code === '510004' ? 'LABA RUGI' : 'NERACA']);
        }
        foreach (['wip_inventory' => '114002', 'raw_material_inventory' => '114003', 'scrap_inventory' => '114005', 'inventory_adjustment' => '510004'] as $key => $account) {
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $account, 'active' => true]);
        }
    }
}