<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrderHistory;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MaterialOrderApprovalTest extends TestCase
{
    use RefreshDatabase;

    private MaterialProcurementService $service;

    private User $requester;

    private User $creator;

    private User $approver;

    private User $outsider;

    private MaterialPurchaseRequest $pr;

    private int $yarnId;

    private int $supplierId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialProcurementService::class);
        $this->requester = User::factory()->create(['name' => 'Pembuat PR Sumber']);
        $this->creator = User::factory()->create(['name' => 'Pembuat PO']);
        $this->approver = User::factory()->create(['name' => 'Approver PO']);
        $this->outsider = User::factory()->create();
        // FIX: izin fixture eksplisit; tidak memberikan akses produksi atau bypass ADMIN.
        config([
            'platform.pr_create_user_ids' => [(string) $this->requester->id],
            'platform.pr_approve_user_ids' => [(string) $this->creator->id],
            'platform.po_view_user_ids' => [],
            'platform.po_create_user_ids' => [(string) $this->creator->id],
            'platform.po_approve_user_ids' => [(string) $this->approver->id],
        ]);
        $this->yarnId = Yarn::create(['yarn_code' => 'YARN-PO-AUTH', 'yarn_type' => 'Cotton', 'unit' => 'KGS'])->id;
        $this->supplierId = Supplier::create(['supplier_code' => 'SUP-PO-AUTH', 'supplier_name' => 'Supplier PO', 'supplier_type' => 'RAW_MATERIAL'])->id;
        $this->pr = $this->service->createRequest(['request_date' => '2026-10-05', 'created_by' => $this->requester->id], [$this->item(100)]);
        $this->service->submitRequest($this->pr->id, $this->requester->id);
        $this->service->approveRequest($this->pr->id, $this->creator->id);
    }

    public function test_create_submit_approve_and_reject_record_exact_history(): void
    {
        $po = $this->order();
        $this->assertHistory($po, 'CREATED', null, 'DRAFT', $this->creator->id);
        $this->service->submitOrder($po->id, $this->creator->id);
        $this->assertHistory($po, 'SUBMITTED', 'DRAFT', 'SUBMITTED', $this->creator->id);
        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->service->approveOrder($po->id, $this->approver->id);
        $this->assertHistory($po, 'APPROVED', 'SUBMITTED', 'APPROVED', $this->approver->id);
        $this->assertSame('APPROVED', $po->fresh()->status);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $po->histories()->pluck('action')->all());
        $other = $this->submitted();
        $this->service->rejectOrder($other->id, 'Harga perlu diperiksa', $this->approver->id);
        $this->assertHistory($other, 'REJECTED', 'SUBMITTED', 'REJECTED', $this->approver->id, 'Harga perlu diperiksa');
        $this->assertSame('DRAFT', $other->fresh()->status);
        $this->assertSame(3, $other->histories()->count());
    }

    public function test_history_rejects_update_save_and_delete(): void
    {
        $po = $this->order();
        $history = $po->histories()->create(['action' => 'EDITED', 'from_status' => 'DRAFT', 'to_status' => 'DRAFT', 'revision_no' => 0, 'actor_id' => $this->creator->id]);
        $before = $history->fresh()->getRawOriginal();
        foreach (['update', 'save', 'delete'] as $operation) {
            $record = $history->fresh();
            $caught = false;
            try {
                if ($operation === 'update') {
                    $record->update(['reason' => 'Tidak sah']);
                } elseif ($operation === 'save') {
                    $record->reason = 'Tidak sah';
                    $record->save();
                } else {
                    $record->delete();
                }
            } catch (RuntimeException $e) {
                $caught = true;
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
            $this->assertTrue($caught);
            $this->assertSame($before, $history->fresh()->getRawOriginal());
        }
    }

    public function test_service_denies_unpermitted_admin_and_non_creator_callers(): void
    {
        $draft = $this->order();
        $submitted = $this->submitted();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        foreach ([$this->outsider->id, $admin->id, null] as $actor) {
            $this->unchanged($draft, AuthorizationException::class, fn () => $this->service->submitOrder($draft->id, $actor));
            $this->unchanged($submitted, AuthorizationException::class, fn () => $this->service->approveOrder($submitted->id, $actor));
            $this->unchanged($submitted, AuthorizationException::class, fn () => $this->service->rejectOrder($submitted->id, 'Alasan penolakan', $actor));
        }
        config(['platform.po_create_user_ids' => [(string) $this->creator->id, (string) $this->outsider->id]]);
        $this->unchanged($draft, AuthorizationException::class, fn () => $this->service->submitOrder($draft->id, $this->outsider->id));
        config(['platform.po_create_user_ids' => []]);
        $this->unchanged($draft, AuthorizationException::class, fn () => $this->service->submitOrder($draft->id, $this->creator->id));
    }

    public function test_http_denies_unpermitted_mutations_before_validation(): void
    {
        $draft = $this->order();
        $submitted = $this->submitted();
        foreach ([$this->outsider, User::factory()->create(['role' => 'ADMIN'])] as $user) {
            $this->actingAs($user)->get(route('mfg.material-orders.create', ['request_id' => $this->pr->id]))->assertForbidden();
            $this->post(route('mfg.material-orders.store'), [])->assertForbidden();
            $this->post(route('mfg.material-orders.submit', $draft->id))->assertForbidden();
            $this->post(route('mfg.material-orders.approve', $submitted->id))->assertForbidden();
            $this->post(route('mfg.material-orders.reject', $submitted->id), ['rejection_reason' => 'Alasan penolakan'])->assertForbidden();
        }
        $this->assertSame('DRAFT', $draft->fresh()->approval_status);
        $this->assertSame('SUBMITTED', $submitted->fresh()->approval_status);
    }

    public function test_service_create_denies_unauthorized_actor_without_reserving_pr_quantity(): void
    {
        foreach ([$this->outsider, User::factory()->create(['role' => 'ADMIN'])] as $user) {
            $caught = false;
            try {
                $this->service->createOrderFromRequest($this->pr->id, $this->supplierId, [$this->orderItem(5)], [
                    'po_date' => '2026-10-05', 'created_by' => $user->id,
                ]);
            } catch (AuthorizationException) {
                $caught = true;
            }
            $this->assertTrue($caught, 'Service create PO wajib memeriksa izin eksplisit.');
            $this->assertSame(0, MaterialPurchaseOrder::count());
            $this->assertEquals(0, $this->pr->details()->sole()->qty_ordered);
        }
    }

    public function test_http_read_edit_and_revision_deny_users_without_permission(): void
    {
        $po = $this->order();
        foreach ([$this->outsider, User::factory()->create(['role' => 'ADMIN'])] as $user) {
            $this->actingAs($user)->get(route('mfg.material-orders.index'))->assertForbidden();
            $this->get(route('mfg.material-orders.show', $po->id))->assertForbidden();
            $this->get(route('mfg.material-orders.edit', $po->id))->assertForbidden();
            $this->put(route('mfg.material-orders.update', $po->id), [])->assertForbidden();
            $this->post(route('mfg.material-orders.revise', $po->id), [])->assertForbidden();
        }
        $this->assertSame('DRAFT', $po->fresh()->approval_status);
        $this->assertSame(1, $po->histories()->count());
    }

    public function test_http_transitions_use_authenticated_actor_and_enforce_sod(): void
    {
        $po = $this->order();
        $this->actingAs($this->creator)->put(route('mfg.material-orders.update', $po->id), [
            'po_date' => '2026-10-06', 'items' => [$this->orderItem(12)], 'remarks' => 'HTTP edit PO',
            'created_by' => $this->outsider->id, 'actor_id' => $this->outsider->id, 'approval_status' => 'APPROVED', 'revision_no' => 99,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame($this->creator->id, $po->fresh()->created_by);
        $this->assertSame('DRAFT', $po->fresh()->approval_status);
        $this->assertSame(0, $po->fresh()->revision_no);
        $this->assertHistory($po, 'EDITED', 'DRAFT', 'DRAFT', $this->creator->id);
        $this->post(route('mfg.material-orders.submit', $po->id), ['actor_id' => $this->outsider->id])->assertSessionHas('success');
        $this->assertEquals($this->creator->id, $po->fresh()->submitted_by);
        config(['platform.po_approve_user_ids' => [(string) $this->creator->id, (string) $this->approver->id]]);
        $this->post(route('mfg.material-orders.approve', $po->id))->assertForbidden();
        $this->post(route('mfg.material-orders.reject', $po->id), ['rejection_reason' => 'Alasan valid'])->assertForbidden();
        $this->actingAs($this->approver)->post(route('mfg.material-orders.reject', $po->id), ['rejection_reason' => '   '])->assertSessionHasErrors('rejection_reason');
        $this->post(route('mfg.material-orders.reject', $po->id), ['rejection_reason' => 'Periksa harga', 'actor_id' => $this->creator->id])->assertSessionHas('success');
        $this->assertEquals($this->approver->id, $po->fresh()->rejected_by);
        $this->actingAs($this->creator)->post(route('mfg.material-orders.revise', $po->id), ['reason' => 'Pendek'])->assertSessionHasErrors('reason');
        $this->post(route('mfg.material-orders.revise', $po->id), ['reason' => 'Harga telah diperiksa kembali', 'actor_id' => $this->outsider->id])->assertSessionHas('success');
        $this->assertHistory($po, 'REVISED', 'REJECTED', 'DRAFT', $this->creator->id, 'Harga telah diperiksa kembali', 1);
        $this->post(route('mfg.material-orders.submit', $po->id))->assertSessionHas('success');
        $this->actingAs($this->approver)->post(route('mfg.material-orders.approve', $po->id), ['actor_id' => $this->creator->id])->assertSessionHas('success');
        $this->assertHistory($po, 'APPROVED', 'SUBMITTED', 'APPROVED', $this->approver->id, null, 1);
    }

    public function test_http_denies_non_owner_and_received_order_changes(): void
    {
        $po = $this->order();
        config(['platform.po_create_user_ids' => [(string) $this->creator->id, (string) $this->outsider->id]]);
        $this->actingAs($this->outsider)->post(route('mfg.material-orders.submit', $po->id))->assertForbidden();
        $this->get(route('mfg.material-orders.edit', $po->id))->assertForbidden();
        $this->put(route('mfg.material-orders.update', $po->id), [])->assertForbidden();
        $po->details()->sole()->update(['qty_received' => 1]);
        $this->actingAs($this->creator)->get(route('mfg.material-orders.edit', $po->id))->assertForbidden();
        $this->put(route('mfg.material-orders.update', $po->id), [])->assertForbidden();
        $po->update(['approval_status' => 'REJECTED']);
        $this->post(route('mfg.material-orders.revise', $po->id), ['reason' => 'Alasan revisi yang valid'])->assertForbidden();
    }

    public function test_sod_denies_po_creator_and_submitter_but_allows_source_pr_creator(): void
    {
        $po = $this->submitted();
        config(['platform.po_approve_user_ids' => [(string) $this->creator->id, (string) $this->approver->id, (string) $this->requester->id]]);
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->approveOrder($po->id, $this->creator->id));
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->rejectOrder($po->id, 'Alasan penolakan', $this->creator->id));
        // FIX: snapshot submitter berbeda untuk membuktikan guard submitter terpisah dari guard pembuat.
        $po->update(['submitted_by' => $this->approver->id]);
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->approveOrder($po->id, $this->approver->id));
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->rejectOrder($po->id, 'Alasan penolakan', $this->approver->id));
        $this->service->approveOrder($po->id, $this->requester->id);
        $this->assertSame('APPROVED', $po->fresh()->approval_status);
        $this->assertEquals($this->requester->id, $po->fresh()->approved_by);
    }

    public function test_reject_requires_reason_and_submitted_status(): void
    {
        $po = $this->submitted();
        foreach (['', '   '] as $reason) {
            $this->unchanged($po, RuntimeException::class, fn () => $this->service->rejectOrder($po->id, $reason, $this->approver->id));
        }
        foreach (['DRAFT', 'APPROVED', 'REJECTED'] as $status) {
            $po->update(['approval_status' => $status]);
            $this->unchanged($po, AuthorizationException::class, fn () => $this->service->approveOrder($po->id, $this->approver->id));
            $this->unchanged($po, AuthorizationException::class, fn () => $this->service->rejectOrder($po->id, 'Alasan valid', $this->approver->id));
        }
    }

    public function test_approval_does_not_change_stock_ledgers_or_journals(): void
    {
        $po = $this->submitted();
        // FIX: hanya membaca saldo/tabel untuk membuktikan approval tidak memposting atau mengubah stok.
        $before = $this->postingSnapshot();
        $this->service->approveOrder($po->id, $this->approver->id);
        $this->assertSame($before, $this->postingSnapshot());
        $this->assertSame('APPROVED', $po->fresh()->approval_status);
    }

    public function test_edit_replaces_details_recalculates_totals_and_pr_reservation(): void
    {
        $po = $this->order(10);
        $oldDetail = $po->details()->sole()->id;
        $this->service->updateOrder($po->id, ['po_date' => '2026-10-06', 'remarks' => 'Edit PO'], [$this->orderItem(15)], $this->creator->id);
        $this->assertSame('Edit PO', $po->fresh()->remarks);
        $this->assertSame('DRAFT', $po->fresh()->approval_status);
        $this->assertEquals(1500, $po->fresh()->grand_total);
        $this->assertEquals(15, $po->details()->sole()->qty);
        $this->assertEquals(15, $this->pr->details()->sole()->qty_ordered);
        $this->assertDatabaseMissing('mfg_material_purchase_order_details', ['id' => $oldDetail]);
        $this->assertHistory($po, 'EDITED', 'DRAFT', 'DRAFT', $this->creator->id);
        $this->unchanged($po, RuntimeException::class, fn () => $this->service->updateOrder($po->id, ['remarks' => 'Melebihi PR'], [$this->orderItem(101)], $this->creator->id));
    }

    public function test_edit_denies_wrong_owner_status_permission_and_received_quantity(): void
    {
        $po = $this->order();
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->updateOrder($po->id, [], [$this->orderItem(5)], $this->outsider->id));
        config(['platform.po_create_user_ids' => []]);
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->updateOrder($po->id, [], [$this->orderItem(5)], $this->creator->id));
        config(['platform.po_create_user_ids' => [(string) $this->creator->id]]);
        foreach (['SUBMITTED', 'APPROVED', 'REJECTED'] as $status) {
            $po->update(['approval_status' => $status]);
            $this->unchanged($po, AuthorizationException::class, fn () => $this->service->updateOrder($po->id, [], [$this->orderItem(5)], $this->creator->id));
        }
        $po->update(['approval_status' => 'DRAFT']);
        $po->details()->sole()->update(['qty_received' => 1]);
        $this->unchanged($po, RuntimeException::class, fn () => $this->service->updateOrder($po->id, [], [$this->orderItem(5)], $this->creator->id));
    }

    public function test_revision_preserves_rejection_history_and_validates_reason_owner_and_status(): void
    {
        $po = $this->submitted();
        $this->service->rejectOrder($po->id, 'Harga perlu diperiksa', $this->approver->id);
        $old = $po->histories()->where('action', 'REJECTED')->sole()->getRawOriginal();
        foreach (['', 'Pendek', str_repeat('a', 1001)] as $reason) {
            $this->unchanged($po, ValidationException::class, fn () => $this->service->reviseOrder($po->id, $reason, $this->creator->id));
        }
        $this->unchanged($po, AuthorizationException::class, fn () => $this->service->reviseOrder($po->id, 'Alasan revisi yang valid', $this->outsider->id));
        $this->service->reviseOrder($po->id, 'Harga telah diperiksa kembali', $this->creator->id);
        $this->assertSame('DRAFT', $po->fresh()->approval_status);
        $this->assertSame('DRAFT', $po->fresh()->status);
        $this->assertSame(1, $po->fresh()->revision_no);
        foreach (['approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason'] as $column) {
            $this->assertNull($po->fresh()->{$column});
        }
        $this->assertSame($old, $po->histories()->where('action', 'REJECTED')->sole()->getRawOriginal());
        $this->assertHistory($po, 'REVISED', 'REJECTED', 'DRAFT', $this->creator->id, 'Harga telah diperiksa kembali', 1);
        foreach (['DRAFT', 'SUBMITTED', 'APPROVED'] as $status) {
            $po->update(['approval_status' => $status]);
            $this->unchanged($po, AuthorizationException::class, fn () => $this->service->reviseOrder($po->id, 'Alasan revisi yang valid', $this->creator->id));
        }
    }

    public function test_history_failure_rolls_back_creation_and_all_transitions(): void
    {
        $draft = $this->order();
        $submitted = $this->submitted();
        // FIX: fixture REJECTED tidak bergantung pada rejectOrder yang masih belum tersedia.
        $rejected = $this->order();
        $rejected->update(['approval_status' => 'REJECTED']);
        $original = MaterialPurchaseOrderHistory::getEventDispatcher();
        $dispatcher = clone $original;
        MaterialPurchaseOrderHistory::setEventDispatcher($dispatcher);
        $dispatcher->listen('eloquent.creating: '.MaterialPurchaseOrderHistory::class, function () {
            throw new RuntimeException('Simulasi gagal histori PO');
        });
        try {
            $count = MaterialPurchaseOrder::count();
            $reserved = $this->pr->details()->sole()->qty_ordered;
            $caught = false;
            try {
                $this->order();
            } catch (RuntimeException $e) {
                $caught = true;
                $this->assertSame('Simulasi gagal histori PO', $e->getMessage());
            }
            $this->assertTrue($caught, 'Create PO wajib membatalkan transaksi bila histori gagal.');
            $this->assertSame($count, MaterialPurchaseOrder::count());
            $this->assertEquals($reserved, $this->pr->details()->sole()->qty_ordered);
            foreach ([
                [$draft, fn () => $this->service->submitOrder($draft->id, $this->creator->id)],
                [$submitted, fn () => $this->service->approveOrder($submitted->id, $this->approver->id)],
                [$submitted, fn () => $this->service->rejectOrder($submitted->id, 'Alasan valid', $this->approver->id)],
                [$draft, fn () => $this->service->updateOrder($draft->id, ['remarks' => 'Edit'], [$this->orderItem(15)], $this->creator->id)],
                [$rejected, fn () => $this->service->reviseOrder($rejected->id, 'Alasan revisi yang valid', $this->creator->id)],
            ] as [$po, $operation]) {
                $this->unchanged($po, RuntimeException::class, $operation, 'Simulasi gagal histori PO');
            }
        } finally {
            MaterialPurchaseOrderHistory::setEventDispatcher($original);
        }
    }

    public function test_po_quantity_cannot_exceed_remaining_approved_pr(): void
    {
        $this->order(95);
        $before = $this->pr->details()->sole()->qty_ordered;
        try {
            $this->order(6);
            $this->fail('PO melebihi sisa PR harus ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sisa detail PR', $e->getMessage());
        }
        $this->assertSame(1, MaterialPurchaseOrder::count());
        $this->assertEquals($before, $this->pr->details()->sole()->qty_ordered);
    }

    public function test_detail_displays_history_and_legacy_notice_only_when_empty(): void
    {
        $po = $this->order();
        $po->histories()->create(['action' => 'EDITED', 'from_status' => 'DRAFT', 'to_status' => 'DRAFT', 'revision_no' => 0, 'actor_id' => $this->creator->id, 'reason' => 'Alasan histori PO unik']);
        $this->actingAs($this->creator)->get(route('mfg.material-orders.show', $po->id))
            ->assertOk()->assertSeeText('EDITED')->assertSeeText('Pembuat PO')->assertSeeText('Alasan histori PO unik')
            ->assertDontSeeText('dibuat sebelum pencatatan histori');
        $legacy = MaterialPurchaseOrder::create(['po_number' => 'PO-LEGACY', 'po_date' => '2026-10-05', 'supplier_id' => $this->supplierId, 'created_by' => $this->creator->id, 'status' => 'DRAFT', 'approval_status' => 'DRAFT']);
        $this->get(route('mfg.material-orders.show', $legacy->id))->assertOk()->assertSeeText('dibuat sebelum pencatatan histori');
    }

    private function order(float $qty = 10): MaterialPurchaseOrder
    {
        return $this->service->createOrderFromRequest($this->pr->id, $this->supplierId, [$this->orderItem($qty)], ['po_date' => '2026-10-05', 'created_by' => $this->creator->id])->fresh();
    }

    public function test_edit_view_preserves_multiple_details_and_old_input(): void
    {
        $po = $this->order();
        $this->service->updateOrder($po->id, ['remarks' => 'Header edit PO'], [$this->orderItem(4), $this->orderItem(6)], $this->creator->id);
        $this->actingAs($this->creator)->get(route('mfg.material-orders.edit', $po->id))
            ->assertOk()->assertSeeText('Supplier PO')->assertSee('Header edit PO')
            ->assertSee('items[0][qty]', false)->assertSee('items[1][qty]', false)
            ->assertSee('items[0][source_request_detail_id]', false)->assertSee('name="_method" value="PUT"', false);
        $this->withSession(['_old_input' => ['remarks' => 'Input lama PO', 'items' => [$this->orderItem(8)]]])
            ->get(route('mfg.material-orders.edit', $po->id))->assertOk()->assertSee('Input lama PO');
    }

    public function test_view_controls_follow_permissions_sod_status_and_receipt_quantity(): void
    {
        $po = $this->order();
        $show = route('mfg.material-orders.show', $po->id);
        $edit = route('mfg.material-orders.edit', $po->id);
        $submit = route('mfg.material-orders.submit', $po->id);
        $approve = route('mfg.material-orders.approve', $po->id);
        $reject = route('mfg.material-orders.reject', $po->id);
        $revise = route('mfg.material-orders.revise', $po->id);
        $this->actingAs($this->creator)->get($show)->assertOk()->assertSee($edit, false)->assertSee($submit, false)->assertDontSee($approve, false);
        config(['platform.po_view_user_ids' => [(string) $this->outsider->id]]);
        $this->actingAs($this->outsider)->get($show)->assertOk()->assertDontSee($edit, false)->assertDontSee($submit, false);
        $this->service->submitOrder($po->id, $this->creator->id);
        $this->actingAs($this->approver)->get($show)->assertOk()->assertSee($approve, false)->assertSee($reject, false)->assertDontSee($edit, false);
        $this->get(route('mfg.material-orders.index'))->assertOk()->assertSee('text-bg-warning', false)->assertSee($approve, false);
        config(['platform.po_approve_user_ids' => [(string) $this->approver->id, (string) $this->creator->id]]);
        $this->actingAs($this->creator)->get($show)->assertOk()->assertDontSee($approve, false)->assertDontSee($reject, false);
        $this->service->rejectOrder($po->id, 'Harga perlu diperiksa', $this->approver->id);
        $this->get($show)->assertOk()->assertSee($revise, false)->assertSee('textarea', false);
        $po->details()->sole()->update(['qty_received' => 1]);
        $this->get($show)->assertOk()->assertDontSee($revise, false);
        $po->update(['approval_status' => 'DRAFT']);
        $this->get($show)->assertOk()->assertDontSee($edit, false);
    }

    public function test_edit_keeps_other_po_reservations_and_duplicate_create_cannot_exceed_pr(): void
    {
        $po = $this->order(10);
        $this->order(80);
        $this->unchanged($po, RuntimeException::class, fn () => $this->service->updateOrder($po->id, [], [$this->orderItem(21)], $this->creator->id));
        $this->service->updateOrder($po->id, [], [$this->orderItem(20)], $this->creator->id);
        $this->assertEquals(100, $this->pr->details()->sole()->qty_ordered);
        $this->assertEquals(20, $po->details()->sole()->qty);
    }

    public function test_duplicate_create_lines_are_bounded_by_total_remaining_pr(): void
    {
        $caught = false;
        try {
            $this->service->createOrderFromRequest($this->pr->id, $this->supplierId, [$this->orderItem(60), $this->orderItem(60)], [
                'po_date' => '2026-10-05', 'created_by' => $this->creator->id,
            ]);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertStringContainsString('sisa detail PR', $e->getMessage());
        }
        $this->assertTrue($caught);
        $this->assertSame(0, MaterialPurchaseOrder::count());
        $this->assertEquals(0, $this->pr->details()->sole()->qty_ordered);
    }

    private function submitted(): MaterialPurchaseOrder
    {
        $po = $this->order();
        $this->service->submitOrder($po->id, $this->creator->id);

        return $po->fresh();
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarnId, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 100];
    }

    private function orderItem(float $qty): array
    {
        return $this->item($qty) + ['source_request_detail_id' => $this->pr->details()->sole()->id];
    }

    private function assertHistory(MaterialPurchaseOrder $po, string $action, ?string $from, string $to, int $actor, ?string $reason = null, int $revision = 0): void
    {
        $rows = $po->histories()->where('action', $action)->where('revision_no', $revision)->get();
        $this->assertCount(1, $rows);
        $row = $rows->sole();
        $this->assertSame($from, $row->from_status);
        $this->assertSame($to, $row->to_status);
        $this->assertEquals($actor, $row->actor_id);
        $this->assertSame($reason, $row->reason);
        $this->assertNotNull($row->created_at);
        $this->assertArrayNotHasKey('updated_at', $row->getAttributes());
    }

    private function unchanged(MaterialPurchaseOrder $po, string $exception, callable $operation, ?string $message = null): void
    {
        $before = $po->fresh()->getRawOriginal();
        $details = $po->details()->orderBy('id')->get()->toArray();
        $history = $po->histories()->get()->toArray();
        $reserved = $this->pr->details()->sole()->qty_ordered;
        $caught = false;
        try {
            $operation();
        } catch (\Exception $e) {
            $caught = true;
            $this->assertInstanceOf($exception, $e);
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }
        $this->assertTrue($caught, 'Operasi PO harus ditolak dengan '.$exception);
        $this->assertSame($before, $po->fresh()->getRawOriginal());
        $this->assertSame($details, $po->details()->orderBy('id')->get()->toArray());
        $this->assertSame($history, $po->histories()->get()->toArray());
        $this->assertEquals($reserved, $this->pr->details()->sole()->qty_ordered);
    }

    private function postingSnapshot(): array
    {
        $tables = [];
        foreach (['journal_headers', 'journal_details', 'inventory_ledgers', 'mfg_material_ledgers'] as $table) {
            $tables[$table] = DB::table($table)->orderBy('id')->get()->toJson();
            $this->assertSame(0, DB::table($table)->count());
        }

        return [$tables, Yarn::findOrFail($this->yarnId)->getRawOriginal()];
    }
}
