<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\ProductBomService;
use App\Modules\Manufacturing\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBomWorkOrderRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_snapshots_bom_requirement_and_material_cost_without_changing_stock(): void
    {
        $product = Product::create(['sku' => 'FG-BOM-01', 'name' => 'Finished Good BOM', 'sell_price' => 0, 'stock_quantity' => 0]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-BOM-01', 'fabric_type' => 'Polyester Fabric', 'description' => 'Polyester Fabric', 'unit' => 'METER', 'stock_quantity' => 500, 'average_cost' => 12_500, 'inventory_account_code' => '114003', 'is_active' => true]);

        app(ProductBomService::class)->save($product->id, [
            'item_type' => 'FABRIC', 'material_id' => $fabric->id, 'qty_per_unit' => 0.058, 'waste_percent' => 2.5,
        ]);

        $workOrder = WorkOrderService::create([
            'order_date' => '2026-09-30', 'product_id' => $product->id, 'garment_name' => 'Finished Good BOM', 'planned_qty' => 200,
        ]);

        $requirement = $workOrder->materialRequirements()->firstOrFail();
        $this->assertSame('FABRIC', $requirement->item_type);
        $this->assertSame('FAB-BOM-01', $requirement->item_code);
        $this->assertSame(11.89, (float) $requirement->qty_required);
        $this->assertSame(12_500.0, (float) $requirement->unit_cost_snapshot);
        $this->assertSame(148_625.0, (float) $requirement->estimated_total_cost);
        $this->assertSame(500.0, (float) $fabric->fresh()->stock_quantity);
        $this->assertSame(12_500.0, (float) $fabric->fresh()->average_cost);

        $fabric->update(['average_cost' => 20_000]);
        $this->assertSame(12_500.0, (float) $requirement->fresh()->unit_cost_snapshot);
        $this->assertSame('DRAFT', WorkOrder::findOrFail($workOrder->id)->status);
    }

    public function test_manual_bom_save_updates_the_same_product_material_component(): void
    {
        $product = Product::create(['sku' => 'FG-BOM-MANUAL', 'name' => 'Finished Good Manual BOM', 'sell_price' => 0, 'stock_quantity' => 0]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-BOM-MANUAL', 'fabric_type' => 'Canvas', 'unit' => 'METER', 'stock_quantity' => 10, 'average_cost' => 10_000, 'inventory_account_code' => '114003', 'is_active' => true]);

        $service = app(ProductBomService::class);
        $service->save($product->id, ['item_type' => 'FABRIC', 'material_id' => $fabric->id, 'qty_per_unit' => 1, 'waste_percent' => 0]);
        $service->save($product->id, ['item_type' => 'FABRIC', 'material_id' => $fabric->id, 'qty_per_unit' => 1.25, 'waste_percent' => 2]);

        $this->assertSame(1, ProductBom::where('product_id', $product->id)->count());
        $bom = ProductBom::where('product_id', $product->id)->sole();
        $this->assertSame(1.25, (float) $bom->qty_per_unit);
        $this->assertSame(2.0, (float) $bom->waste_percent);
    }
}