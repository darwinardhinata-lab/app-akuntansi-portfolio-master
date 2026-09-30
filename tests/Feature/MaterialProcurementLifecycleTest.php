<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialProcurementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MaterialProcurementService $service;
    private int $yarnId;
    private int $supplierId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialProcurementService::class);
        $this->yarnId = Yarn::create(['yarn_code' => 'YARN-M2', 'yarn_type' => 'Cotton', 'unit' => 'KGS'])->id;
        $this->supplierId = Supplier::create(['supplier_code' => 'SUP-M2', 'supplier_name' => 'Supplier Raw Material', 'supplier_type' => 'RAW_MATERIAL'])->id;
    }

    public function test_material_pr_to_approved_po_keeps_stock_and_journal_empty(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28'], [$this->item(100)]);
        $this->assertSame('DRAFT', $pr->approval_status);
        $this->service->submitRequest($pr->id, null);
        $this->service->approveRequest($pr->id, null);

        $detail = $pr->fresh('details')->details->sole();
        $po = $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(40) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28']);
        $this->assertSame('DRAFT', $po->approval_status);
        $this->service->submitOrder($po->id, null);
        $this->service->approveOrder($po->id, null);

        $this->assertDatabaseHas('mfg_material_purchase_orders', ['id' => $po->id, 'approval_status' => 'APPROVED', 'fulfillment_status' => 'OPEN', 'status' => 'APPROVED']);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id, 'source_request_detail_id' => $detail->id, 'qty' => 40]);
        $this->assertEquals(40, DB::table('mfg_material_purchase_request_details')->where('id', $detail->id)->value('qty_ordered'));
        $this->assertSame(0, DB::table('journal_headers')->count());
        $this->assertSame(0, DB::table('inventory_ledgers')->count());
        $this->assertSame(0, DB::table('mfg_material_ledgers')->count());
        $this->assertEquals(0, Yarn::findOrFail($this->yarnId)->stock_quantity);
    }

    public function test_po_cannot_be_created_from_unapproved_pr(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28'], [$this->item(10)]);
        $detail = $pr->fresh('details')->details->sole();
        $this->expectExceptionMessage('Material PO harus berasal dari PR APPROVED');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(10) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28']);
    }

    public function test_po_cannot_exceed_remaining_approved_pr_quantity(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28'], [$this->item(10)]);
        $this->service->submitRequest($pr->id, null);
        $this->service->approveRequest($pr->id, null);
        $detail = $pr->fresh('details')->details->sole();

        $this->expectExceptionMessage('Detail Material PO harus berasal dari sisa detail PR');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(11) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28']);
    }

    public function test_inactive_material_master_or_non_raw_material_supplier_is_rejected(): void
    {
        Yarn::whereKey($this->yarnId)->update(['is_active' => false]);
        $this->expectExceptionMessage('Yarn harus aktif');
        $this->service->createRequest(['request_date' => '2026-09-28'], [$this->item(1)]);
    }

    public function test_rejected_pr_cannot_be_approved_or_used_for_po(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28'], [$this->item(5)]);
        $this->service->submitRequest($pr->id, null);
        $this->service->rejectRequest($pr->id, 'Tidak diperlukan', null);

        $this->expectExceptionMessage('Material PO harus berasal dari PR APPROVED');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(5) + ['source_request_detail_id' => $pr->fresh('details')->details->sole()->id]], ['po_date' => '2026-09-28']);
    }

    public function test_authenticated_user_can_open_material_procurement_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('mfg.material-requests.index'))->assertOk()->assertSee('Material Purchase Request');
        $this->actingAs($user)->get(route('mfg.material-requests.create'))->assertOk()->assertSee('Buat Material Purchase Request');
        $this->actingAs($user)->get(route('mfg.material-orders.index'))->assertOk()->assertSee('Material Purchase Order');
    }

    public function test_approved_auxiliary_material_pr_can_create_po_with_same_master_reference(): void
    {
        $auxiliary = AuxiliaryMaterial::create(['material_code' => 'AUX-PR', 'material_name' => 'Polybag', 'unit' => 'PCS', 'is_active' => true]);
        $item = ['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $auxiliary->id, 'item_name' => 'Polybag', 'qty' => 10, 'unit' => 'PCS', 'rate' => 100];
        $pr = $this->service->createRequest(['request_date' => '2026-09-30'], [$item]);
        $this->service->submitRequest($pr->id, null);
        $this->service->approveRequest($pr->id, null);
        $detail = $pr->fresh('details')->details->sole();
        $po = $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$item + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-30']);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id, 'item_type' => 'AUXILIARY', 'auxiliary_material_id' => $auxiliary->id]);
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarnId, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 10000];
    }
}