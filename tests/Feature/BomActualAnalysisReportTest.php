<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomActualAnalysisReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bom_actual_report_reads_work_order_snapshot_and_does_not_mutate_inventory(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::create(['sku' => 'FG-REPORT-01', 'name' => 'Finished Good Report', 'sell_price' => 0, 'stock_quantity' => 0]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-REPORT-01', 'fabric_type' => 'Report Fabric', 'description' => 'Report Fabric', 'unit' => 'METER', 'stock_quantity' => 50, 'average_cost' => 10_000, 'inventory_account_code' => '114003', 'is_active' => true]);
        ProductBom::create(['product_id' => $product->id, 'item_type' => 'FABRIC', 'fabric_id' => $fabric->id, 'qty_per_unit' => 2, 'waste_percent' => 5, 'is_active' => true]);
        $workOrder = WorkOrderService::create(['order_date' => '2026-09-30', 'product_id' => $product->id, 'garment_name' => 'Finished Good Report', 'planned_qty' => 10]);
        $requirementCount = $workOrder->materialRequirements()->count();

        $this->get(route('mfg.reports.bom-actual', ['start_date' => '2026-09-01', 'end_date' => '2026-09-30']))
            ->assertOk()->assertSee('Analisis BOM vs Aktual')->assertSee($workOrder->spk_number)->assertSee('FAB-REPORT-01')->assertSee('21.000000');

        $this->assertSame($requirementCount, $workOrder->materialRequirements()->count());
        $this->assertSame(50.0, (float) $fabric->fresh()->stock_quantity);
        $this->assertSame(10_000.0, (float) $fabric->fresh()->average_cost);
        $this->assertSame('DRAFT', WorkOrder::findOrFail($workOrder->id)->status);
    }
}