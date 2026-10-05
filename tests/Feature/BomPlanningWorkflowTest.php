<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Modules\Manufacturing\Imports\ProductBomImport;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Services\WorkOrderMaterialPlanningService;
use App\Modules\Manufacturing\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BomPlanningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bom_import_is_idempotent_and_shortage_pr_uses_bom_snapshot_without_changing_stock(): void
    {
        $product = Product::create(['sku' => 'FG-BOM-PR', 'name' => 'Finished Good', 'sell_price' => 0, 'stock_quantity' => 0]);
        $material = AuxiliaryMaterial::create(['material_code' => 'AUX-BOM-01', 'material_name' => 'Label', 'unit' => 'PCS', 'stock_quantity' => 3, 'average_cost' => 100, 'inventory_account_code' => '114004', 'is_active' => true]);
        $row = ['FG-BOM-PR', 'Finished Good', 'AUXILIARY', 'AUX-BOM-01', '2', '0', 'Label per unit', 'AKTIF'];

        app(ProductBomImport::class)->collection(new Collection([$row]));
        app(ProductBomImport::class)->collection(new Collection([$row]));
        $this->assertSame(1, ProductBom::count());

        $workOrder = WorkOrderService::create(['order_date' => '2026-09-30', 'product_id' => $product->id, 'garment_name' => 'Finished Good', 'planned_qty' => 5]);
        $workOrder->load('materialRequirements');
        $this->assertSame(10.0, (float) $workOrder->materialRequirements->sole()->qty_required);

        $service = app(WorkOrderMaterialPlanningService::class);
        $comparison = $service->comparison($workOrder)->sole();
        $this->assertSame(0.0, $comparison->actual_qty);
        $this->assertSame(10.0, $comparison->variance_qty);

        $preview = $service->shortagePreview($workOrder)->sole();
        $this->assertSame(10.0, (float) $preview->requirement->qty_required);
        $this->assertSame(3.0, $preview->stock_available);
        $this->assertSame(7.0, $preview->shortage_qty);
        $this->assertSame(700.0, $preview->shortage_estimated_cost);

        // FIX: generator PR memakai pembuat dengan izin eksplisit, bukan actor kosong.
        $creator = User::factory()->create();
        config(['platform.pr_create_user_ids' => [(string) $creator->id]]);
        $request = $service->generateShortageRequest($workOrder, $creator->id);
        $this->assertSame($workOrder->id, $request->source_work_order_id);
        $this->assertSame(7.0, (float) $request->details()->sole()->qty_requested);
        $this->assertSame(3.0, (float) $material->fresh()->stock_quantity);
        $this->expectException(\RuntimeException::class);
        $service->generateShortageRequest($workOrder, $creator->id);
    }
}
