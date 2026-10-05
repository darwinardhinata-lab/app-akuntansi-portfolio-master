<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
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

    private User $prCreator;

    private User $prApprover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialProcurementService::class);
        $this->prCreator = User::factory()->create(['name' => 'Pembuat PR Lifecycle']);
        $this->prApprover = User::factory()->create(['name' => 'Penyetuju PR Lifecycle']);
        // FIX: izin eksplisit per user via allowlist config (default deny), tanpa grant role permanen.
        config([
            'platform.pr_create_user_ids' => [(string) $this->prCreator->id],
            'platform.pr_approve_user_ids' => [(string) $this->prApprover->id],
            'platform.po_create_user_ids' => [(string) $this->prCreator->id],
            'platform.po_approve_user_ids' => [(string) $this->prApprover->id],
        ]);
        $this->yarnId = Yarn::create(['yarn_code' => 'YARN-M2', 'yarn_type' => 'Cotton', 'unit' => 'KGS'])->id;
        $this->supplierId = Supplier::create(['supplier_code' => 'SUP-M2', 'supplier_name' => 'Supplier Raw Material', 'supplier_type' => 'RAW_MATERIAL'])->id;
    }

    public function test_material_pr_to_approved_po_keeps_stock_and_journal_empty(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28', 'created_by' => $this->prCreator->id], [$this->item(100)]);
        $this->assertSame('DRAFT', $pr->approval_status);
        $this->service->submitRequest($pr->id, $this->prCreator->id);
        $this->service->approveRequest($pr->id, $this->prApprover->id);

        $detail = $pr->fresh('details')->details->sole();
        $po = $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(40) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28', 'created_by' => $this->prCreator->id]);
        $this->assertSame('DRAFT', $po->approval_status);
        $this->service->submitOrder($po->id, $this->prCreator->id);
        $this->service->approveOrder($po->id, $this->prApprover->id);

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
        $pr = $this->service->createRequest(['request_date' => '2026-09-28', 'created_by' => $this->prCreator->id], [$this->item(10)]);
        $detail = $pr->fresh('details')->details->sole();
        $this->expectExceptionMessage('Material PO harus berasal dari PR APPROVED');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(10) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28', 'created_by' => $this->prCreator->id]);
    }

    public function test_po_cannot_exceed_remaining_approved_pr_quantity(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28', 'created_by' => $this->prCreator->id], [$this->item(10)]);
        $this->service->submitRequest($pr->id, $this->prCreator->id);
        $this->service->approveRequest($pr->id, $this->prApprover->id);
        $detail = $pr->fresh('details')->details->sole();

        $this->expectExceptionMessage('Detail Material PO harus berasal dari sisa detail PR');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(11) + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28', 'created_by' => $this->prCreator->id]);
    }

    public function test_inactive_material_master_or_non_raw_material_supplier_is_rejected(): void
    {
        Yarn::whereKey($this->yarnId)->update(['is_active' => false]);
        $this->expectExceptionMessage('Yarn harus aktif');
        $this->service->createRequest(['request_date' => '2026-09-28', 'created_by' => $this->prCreator->id], [$this->item(1)]);
    }

    public function test_rejected_pr_cannot_be_approved_or_used_for_po(): void
    {
        $pr = $this->service->createRequest(['request_date' => '2026-09-28', 'created_by' => $this->prCreator->id], [$this->item(5)]);
        $this->service->submitRequest($pr->id, $this->prCreator->id);
        $this->service->rejectRequest($pr->id, 'Tidak diperlukan', $this->prApprover->id);

        $this->expectExceptionMessage('Material PO harus berasal dari PR APPROVED');
        $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$this->item(5) + ['source_request_detail_id' => $pr->fresh('details')->details->sole()->id]], ['po_date' => '2026-09-28', 'created_by' => $this->prCreator->id]);
    }

    public function test_authenticated_user_can_open_material_procurement_pages(): void
    {
        $user = User::factory()->create();
        // FIX: izin create eksplisit agar halaman create tetap terbuka setelah otorisasi Tahap 2.
        config(['platform.pr_create_user_ids' => [(string) $user->id]]);

        $this->actingAs($user)->get(route('mfg.material-requests.index'))->assertOk()->assertSee('Purchase Request');
        $this->actingAs($user)->get(route('mfg.material-requests.create'))->assertOk()->assertSee('Buat Material Purchase Request');
        $this->actingAs($user)->get(route('mfg.material-orders.index'))->assertOk()->assertSee('Material Purchase Order');
    }

    public function test_purchase_request_index_exposes_marvel_tabs_and_overview(): void
    {
        $user = User::factory()->create();
        // FIX: izin create eksplisit untuk pembuat tab overview ini.
        config(['platform.pr_create_user_ids' => [(string) $user->id]]);
        $this->service->createRequest([
            'request_date' => '2026-10-02',
            'created_by' => $user->id,
            'remarks' => 'Kebutuhan produksi Oktober',
        ], [$this->item(12)]);

        $this->actingAs($user)->get(route('mfg.material-requests.index'))
            ->assertOk()
            ->assertSeeText('Overview')
            ->assertSeeText('PR List')
            ->assertSeeText('My PR')
            ->assertSeeText('Total PR')
            ->assertSeeText('Kebutuhan produksi Oktober');
    }

    public function test_my_pr_scope_cannot_be_bypassed_by_search_matching_another_request(): void
    {
        $requester = User::factory()->create(['name' => 'Requester Sendiri']);
        $other = User::factory()->create(['name' => 'Requester Lain']);
        // FIX: izin create eksplisit untuk kedua pembuat pada uji My PR ini.
        config(['platform.pr_create_user_ids' => [(string) $requester->id, (string) $other->id]]);
        $mine = $this->service->createRequest([
            'request_date' => '2026-10-02', 'created_by' => $requester->id, 'remarks' => 'Kebutuhan sendiri unik',
        ], [$this->item(4)]);
        $theirs = $this->service->createRequest([
            'request_date' => '2026-10-02', 'created_by' => $other->id, 'remarks' => 'Kata pencarian rahasia',
        ], [$this->item(5)]);

        $this->actingAs($requester)
            ->get(route('mfg.material-requests.index', ['tab' => 'mine']))
            ->assertOk()->assertSeeText($mine->request_number)->assertDontSeeText($theirs->request_number);

        $this->actingAs($requester)
            ->get(route('mfg.material-requests.index', ['tab' => 'mine', 'search' => 'Kata pencarian rahasia']))
            ->assertOk()->assertDontSeeText($theirs->request_number)->assertSeeText('Tidak ada Purchase Request yang sesuai.');
    }

    public function test_purchase_request_list_can_search_requester_and_filter_status(): void
    {
        $viewer = User::factory()->create();
        config(['platform.pr_view_user_ids' => [(string) $viewer->id]]);
        $requester = User::factory()->create(['name' => 'Pemohon Filter Khusus']);
        // FIX: izin create eksplisit untuk pembuat pada uji filter dan search ini.
        config(['platform.pr_create_user_ids' => [(string) $requester->id]]);
        $draft = $this->service->createRequest([
            'request_date' => '2026-10-02', 'created_by' => $requester->id, 'remarks' => 'Purpose draft khusus',
        ], [$this->item(6)]);
        $submitted = $this->service->createRequest([
            'request_date' => '2026-10-02', 'created_by' => $requester->id, 'remarks' => 'Purpose submitted khusus',
        ], [$this->item(7)]);
        $this->service->submitRequest($submitted->id, $requester->id);

        $this->actingAs($viewer)
            ->get(route('mfg.material-requests.index', ['tab' => 'list', 'search' => 'Pemohon Filter', 'status' => MaterialPurchaseRequest::SUBMITTED]))
            ->assertOk()->assertSeeText($submitted->request_number)->assertDontSeeText($draft->request_number);

        $this->actingAs($viewer)
            ->get(route('mfg.material-requests.index', ['tab' => 'list', 'requester_id' => $requester->id, 'status' => MaterialPurchaseRequest::DRAFT]))
            ->assertOk()->assertSeeText($draft->request_number)->assertDontSeeText($submitted->request_number);
    }

    public function test_purchase_request_detail_shows_material_and_available_status_history(): void
    {
        $requester = User::factory()->create(['name' => 'Pembuat PR Detail']);
        $approver = User::factory()->create(['name' => 'Penyetuju PR Detail']);
        // FIX: izin create dan approve eksplisit untuk user pada uji detail ini.
        config([
            'platform.pr_create_user_ids' => [(string) $requester->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
        ]);
        $pr = $this->service->createRequest([
            'request_date' => '2026-10-02', 'created_by' => $requester->id, 'remarks' => 'Purpose detail audit',
        ], [$this->item(8)]);
        $this->service->submitRequest($pr->id, $requester->id);
        $this->service->approveRequest($pr->id, $approver->id);

        $this->actingAs($requester)->get(route('mfg.material-requests.show', $pr->id))
            ->assertOk()
            ->assertSeeText($pr->request_number)
            ->assertSeeText('Cotton Yarn')
            ->assertSeeText('Pembuat PR Detail')
            ->assertSeeText('Penyetuju PR Detail')
            ->assertSeeText('Purpose detail audit')
            ->assertSeeText('History PR')
            ->assertSeeText('CREATED')
            ->assertSeeText('SUBMITTED')
            ->assertSeeText('APPROVED')
            ->assertDontSeeText('belum merupakan approval history append-only');
    }

    public function test_purchase_request_index_rejects_unknown_tab_and_status(): void
    {
        $user = User::factory()->create();
        config(['platform.pr_view_user_ids' => [(string) $user->id]]);

        $this->actingAs($user)->get(route('mfg.material-requests.index', ['tab' => 'unknown']))->assertSessionHasErrors('tab');
        $this->actingAs($user)->get(route('mfg.material-requests.index', ['status' => 'PAID']))->assertSessionHasErrors('status');
    }

    public function test_approved_auxiliary_material_pr_can_create_po_with_same_master_reference(): void
    {
        $auxiliary = AuxiliaryMaterial::create(['material_code' => 'AUX-PR', 'material_name' => 'Polybag', 'unit' => 'PCS', 'is_active' => true]);
        $item = ['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $auxiliary->id, 'item_name' => 'Polybag', 'qty' => 10, 'unit' => 'PCS', 'rate' => 100];
        $pr = $this->service->createRequest(['request_date' => '2026-09-30', 'created_by' => $this->prCreator->id], [$item]);
        $this->service->submitRequest($pr->id, $this->prCreator->id);
        $this->service->approveRequest($pr->id, $this->prApprover->id);
        $detail = $pr->fresh('details')->details->sole();
        $po = $this->service->createOrderFromRequest($pr->id, $this->supplierId, [$item + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-30', 'created_by' => $this->prCreator->id]);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id, 'item_type' => 'AUXILIARY', 'auxiliary_material_id' => $auxiliary->id]);
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarnId, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 10000];
    }
}
