<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\CuttingOrderService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LinePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutting_requires_active_production_line_and_snapshots_it_on_wip_issue(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        $this->account('114002', 'Barang dalam proses', 'ASSET', 'DEBET');
        $this->account('114003', 'Bahan baku', 'ASSET', 'DEBET');
        foreach (['wip_inventory' => '114002', 'raw_material_inventory' => '114003'] as $semanticKey => $accountCode) {
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $semanticKey, 'account_code' => $accountCode, 'active' => true]);
        }
        $fabric = Fabric::create(['fabric_code' => 'FAB-LINE', 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 20, 'average_cost' => 100]);
        $withoutLine = WorkOrder::create(['spk_number' => 'MFG-NO-LINE', 'order_date' => '2026-09-30', 'garment_name' => 'No Line', 'planned_qty' => 10]);

        try {
            app(CuttingOrderService::class)->create($withoutLine->id, $fabric->id, $this->payload());
            $this->fail('Cutting tanpa Line Produksi harus ditolak.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('belum memiliki Line Produksi', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('mfg_cutting_orders')->count());
        $this->assertEquals(20, $fabric->fresh()->stock_quantity);

        $line = ProductionLine::create(['line_code' => 'CUT-01', 'line_name' => 'Cutting Line 01', 'is_active' => true]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-LINE', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'With Line', 'planned_qty' => 10]);
        $cutting = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, $this->payload());

        $this->assertSame($line->id, $cutting->line_id);
        $this->assertDatabaseHas('mfg_cutting_orders', ['id' => $cutting->id, 'line_id' => $line->id, 'fabric_total_cost' => 500]);
        $this->assertEquals(15, $fabric->fresh()->stock_quantity);
        $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => $cutting->cutting_order_number, 'type' => 'OUT', 'total_cost' => 500]);
        $this->assertDatabaseHas('journal_details', ['account_code' => '114002', 'position' => 'DEBET', 'amount' => 500]);
        $this->assertDatabaseHas('journal_details', ['account_code' => '114003', 'position' => 'KREDIT', 'amount' => 500]);
    }

    private function payload(): array
    {
        return ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10];
    }

    private function account(string $code, string $name, string $type, string $normal): void
    {
        Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => $type, 'normal_balance' => $normal, 'report_pos' => 'NERACA']);
    }
}