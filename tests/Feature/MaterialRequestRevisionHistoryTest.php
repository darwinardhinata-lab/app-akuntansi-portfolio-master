<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequestHistory;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MaterialRequestRevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private MaterialProcurementService $service;

    private User $creator;

    private User $approver;

    private User $outsider;

    private int $yarnId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialProcurementService::class);
        $this->creator = User::factory()->create(['name' => 'Pembuat Histori PR']);
        $this->approver = User::factory()->create(['name' => 'Approver Histori PR']);
        $this->outsider = User::factory()->create();
        $this->yarnId = Yarn::create(['yarn_code' => 'YARN-REV', 'yarn_type' => 'Cotton', 'unit' => 'KGS'])->id;
        // FIX: izin hanya untuk fixture test; konfigurasi produksi tetap default deny.
        config([
            'platform.pr_view_user_ids' => [],
            'platform.pr_create_user_ids' => [(string) $this->creator->id],
            'platform.pr_approve_user_ids' => [(string) $this->approver->id],
        ]);
    }

    public function test_create_submit_approve_and_reject_append_exactly_one_history_per_action(): void
    {
        $pr = $this->draft();
        $this->assertHistory($pr, 'CREATED', null, 'DRAFT', $this->creator->id);
        $this->service->submitRequest($pr->id, $this->creator->id);
        $this->assertHistory($pr, 'SUBMITTED', 'DRAFT', 'SUBMITTED', $this->creator->id);
        $this->service->approveRequest($pr->id, $this->approver->id);
        $this->assertHistory($pr, 'APPROVED', 'SUBMITTED', 'APPROVED', $this->approver->id);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $pr->histories()->pluck('action')->all());

        $rejected = $this->draft();
        $this->service->submitRequest($rejected->id, $this->creator->id);
        $this->service->rejectRequest($rejected->id, 'Stok masih mencukupi', $this->approver->id);
        $this->assertHistory($rejected, 'REJECTED', 'SUBMITTED', 'REJECTED', $this->approver->id, 'Stok masih mencukupi');
        $this->assertSame(3, $rejected->histories()->count());
    }

    public function test_history_rejects_update_save_and_delete_without_changing_data(): void
    {
        $pr = $this->draft();
        // FIX: fixture histori langsung menguji guard model tanpa menunggu service menulis histori.
        $history = $pr->histories()->create([
            'action' => 'EDITED', 'from_status' => 'DRAFT', 'to_status' => 'DRAFT',
            'revision_no' => 0, 'actor_id' => $this->creator->id, 'reason' => 'Histori asli',
        ]);
        $before = $history->fresh()->getRawOriginal();
        foreach (['update', 'save', 'delete'] as $operation) {
            $record = $history->fresh();
            try {
                if ($operation === 'update') {
                    $record->update(['reason' => 'Diubah']);
                } elseif ($operation === 'save') {
                    $record->reason = 'Diubah';
                    $record->save();
                } else {
                    $record->delete();
                }
                $this->fail('Histori harus menolak '.$operation);
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
            $this->assertSame($before, $history->fresh()->getRawOriginal());
        }
    }

    public function test_creator_can_edit_draft_and_replace_details_atomically(): void
    {
        $pr = $this->draft();
        $oldId = $pr->details()->sole()->id;
        $this->service->updateRequest($pr->id, [
            'request_date' => '2026-10-04', 'required_date' => '2026-10-10', 'remarks' => 'Header diperbarui',
        ], [$this->item(9), $this->item(3)], $this->creator->id);

        $fresh = $pr->fresh();
        $this->assertSame('DRAFT', $fresh->approval_status);
        $this->assertSame(0, $fresh->revision_no);
        $this->assertSame('Header diperbarui', $fresh->remarks);
        $this->assertSame('2026-10-04', $fresh->request_date->format('Y-m-d'));
        $this->assertSame('2026-10-10', $fresh->required_date->format('Y-m-d'));
        $this->assertSame($this->creator->id, $fresh->created_by);
        $this->assertSame($pr->request_number, $fresh->request_number);
        $this->assertSame(2, $fresh->details()->count());
        $this->assertEquals([9, 3], $fresh->details()->orderBy('id')->pluck('qty_requested')->all());
        $this->assertDatabaseMissing('mfg_material_purchase_request_details', ['id' => $oldId]);
        $this->assertHistory($pr, 'EDITED', 'DRAFT', 'DRAFT', $this->creator->id);
    }

    public function test_edit_denies_non_creator_disallowed_status_and_revoked_permission(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $pr = $this->draft();
        config(['platform.pr_create_user_ids' => [(string) $this->creator->id, (string) $this->outsider->id]]);
        foreach ([$this->outsider->id, $admin->id] as $actorId) {
            $this->assertDeniedUnchanged($pr, fn () => $this->service->updateRequest($pr->id, ['remarks' => 'Tidak sah'], [$this->item(1)], $actorId));
        }
        config(['platform.pr_create_user_ids' => []]);
        $this->assertDeniedUnchanged($pr, fn () => $this->service->updateRequest($pr->id, ['remarks' => 'Tidak sah'], [$this->item(1)], $this->creator->id));
        config(['platform.pr_create_user_ids' => [(string) $this->creator->id]]);
        foreach (['SUBMITTED', 'APPROVED', 'REJECTED'] as $status) {
            // FIX: state fixture terisolasi untuk menguji penolakan status tanpa bergantung pada transisi baru.
            $pr->update(['approval_status' => $status]);
            $this->assertDeniedUnchanged($pr, fn () => $this->service->updateRequest($pr->id, ['remarks' => 'Tidak sah'], [$this->item(1)], $this->creator->id));
        }
    }

    public function test_revision_preserves_rejection_history_and_clears_snapshots(): void
    {
        $pr = $this->rejected();
        $rejection = $pr->histories()->where('action', 'REJECTED')->sole()->getRawOriginal();
        $details = $pr->details()->get()->toArray();
        $this->service->reviseRequest($pr->id, 'Kebutuhan produksi diperiksa kembali', $this->creator->id);
        $fresh = $pr->fresh();
        $this->assertSame('DRAFT', $fresh->approval_status);
        $this->assertSame(1, $fresh->revision_no);
        foreach (['approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason'] as $column) {
            $this->assertNull($fresh->{$column});
        }
        $this->assertSame($details, $fresh->details()->get()->toArray());
        $this->assertSame($rejection, $fresh->histories()->where('action', 'REJECTED')->sole()->getRawOriginal());
        $this->assertHistory($fresh, 'REVISED', 'REJECTED', 'DRAFT', $this->creator->id, 'Kebutuhan produksi diperiksa kembali', 1);
        $this->service->submitRequest($pr->id, $this->creator->id);
        $this->assertHistory($fresh, 'SUBMITTED', 'DRAFT', 'SUBMITTED', $this->creator->id, null, 1);
        $this->assertSame(5, $fresh->histories()->count());
    }

    public function test_revision_rejects_invalid_reason_non_creator_and_wrong_status(): void
    {
        $pr = $this->rejected();
        foreach (['', 'Pendek', str_repeat('a', 1001)] as $reason) {
            $this->assertFailureUnchanged($pr, ValidationException::class,
                fn () => $this->service->reviseRequest($pr->id, $reason, $this->creator->id));
        }
        config(['platform.pr_create_user_ids' => [(string) $this->creator->id, (string) $this->outsider->id]]);
        $this->assertDeniedUnchanged($pr,
            fn () => $this->service->reviseRequest($pr->id, 'Alasan revisi yang valid', $this->outsider->id));
        foreach (['DRAFT', 'SUBMITTED', 'APPROVED'] as $status) {
            $pr->update(['approval_status' => $status]);
            $this->assertDeniedUnchanged($pr,
                fn () => $this->service->reviseRequest($pr->id, 'Alasan revisi yang valid', $this->creator->id));
        }
    }

    public function test_ordered_quantity_blocks_both_edit_and_revision(): void
    {
        $draft = $this->draft();
        $draft->details()->sole()->update(['qty_ordered' => 1]);
        $this->assertFailureUnchanged($draft, RuntimeException::class,
            fn () => $this->service->updateRequest($draft->id, ['remarks' => 'Tidak boleh berubah'], [$this->item(2)], $this->creator->id));

        $rejected = $this->rejected();
        $rejected->details()->sole()->update(['qty_ordered' => 1]);
        $this->assertFailureUnchanged($rejected, RuntimeException::class,
            fn () => $this->service->reviseRequest($rejected->id, 'Alasan revisi yang valid', $this->creator->id));
    }

    public function test_history_write_failure_rolls_back_create_and_every_mutation(): void
    {
        $draft = $this->draft();
        $submitted = $this->draft();
        $this->service->submitRequest($submitted->id, $this->creator->id);
        $rejected = $this->rejected();
        $failHistory = false;
        // FIX: dispatcher disalin dan dipulihkan agar listener test tidak mengganggu event lain.
        $event = 'eloquent.creating: '.MaterialPurchaseRequestHistory::class;
        $originalDispatcher = MaterialPurchaseRequestHistory::getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        MaterialPurchaseRequestHistory::setEventDispatcher($dispatcher);
        $dispatcher->listen($event, function () use (&$failHistory) {
            if ($failHistory) {
                throw new RuntimeException('Simulasi gagal menulis histori PR');
            }
        });
        $failHistory = true;
        try {
            $count = MaterialPurchaseRequest::count();
            $detailCount = $draft->details()->getModel()->count();
            $caught = false;
            try {
                $this->draft();
            } catch (RuntimeException $e) {
                $caught = true;
                $this->assertSame('Simulasi gagal menulis histori PR', $e->getMessage());
            }
            $this->assertTrue($caught, 'Create harus rollback bila insert histori gagal.');
            $this->assertSame($count, MaterialPurchaseRequest::count());
            $this->assertSame($detailCount, $draft->details()->getModel()->count());

            $operations = [
                [$draft, fn () => $this->service->submitRequest($draft->id, $this->creator->id)],
                [$submitted, fn () => $this->service->approveRequest($submitted->id, $this->approver->id)],
                [$submitted, fn () => $this->service->rejectRequest($submitted->id, 'Alasan penolakan', $this->approver->id)],
                [$draft, fn () => $this->service->updateRequest($draft->id, ['remarks' => 'Perubahan atomik'], [$this->item(8)], $this->creator->id)],
                [$rejected, fn () => $this->service->reviseRequest($rejected->id, 'Alasan revisi yang valid', $this->creator->id)],
            ];
            foreach ($operations as [$request, $operation]) {
                $this->assertFailureUnchanged($request, RuntimeException::class, $operation, 'Simulasi gagal menulis histori PR');
            }
        } finally {
            $failHistory = false;
            MaterialPurchaseRequestHistory::setEventDispatcher($originalDispatcher);
        }
    }

    public function test_detail_displays_history_rows_and_not_legacy_notice(): void
    {
        $pr = $this->draft();
        // FIX: histori fixture eksplisit memisahkan test tampilan dari implementasi penulisan service.
        $pr->histories()->create([
            'action' => 'EDITED', 'from_status' => 'DRAFT', 'to_status' => 'DRAFT',
            'revision_no' => 0, 'actor_id' => $this->creator->id, 'reason' => 'Alasan histori tampilan unik',
        ]);
        $this->actingAs($this->creator)->get(route('mfg.material-requests.show', $pr->id))
            ->assertOk()->assertSeeText('EDITED')->assertSeeText('Pembuat Histori PR')
            ->assertSeeText('Alasan histori tampilan unik')->assertSeeText('DRAFT')
            ->assertDontSeeText('PR dibuat sebelum pencatatan histori')
            ->assertDontSeeText('belum merupakan approval history append-only');
    }

    public function test_detail_of_legacy_pr_without_history_displays_legacy_notice(): void
    {
        // FIX: PR legacy dibuat langsung, tanpa service dan tanpa backfill histori.
        $legacy = MaterialPurchaseRequest::create([
            'request_number' => 'MPR-LEGACY-HISTORY', 'request_date' => '2026-10-01',
            'approval_status' => 'DRAFT', 'created_by' => $this->creator->id,
        ]);
        $this->assertSame(0, $legacy->histories()->count());
        $this->actingAs($this->creator)->get(route('mfg.material-requests.show', $legacy->id))
            ->assertOk()->assertSeeText('PR dibuat sebelum pencatatan histori');
    }

    private function draft(): MaterialPurchaseRequest
    {
        return $this->service->createRequest([
            'request_date' => '2026-10-03', 'created_by' => $this->creator->id, 'remarks' => 'Header awal',
        ], [$this->item(5)])->fresh();
    }

    private function rejected(): MaterialPurchaseRequest
    {
        $pr = $this->draft();
        $this->service->submitRequest($pr->id, $this->creator->id);
        $this->service->rejectRequest($pr->id, 'Stok masih mencukupi', $this->approver->id);

        return $pr->fresh();
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarnId, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS'];
    }

    private function assertHistory(MaterialPurchaseRequest $pr, string $action, ?string $from, string $to, int $actorId, ?string $reason = null, int $revision = 0): void
    {
        $rows = $pr->histories()->where('action', $action)->where('revision_no', $revision)->get();
        $this->assertCount(1, $rows);
        $history = $rows->sole();
        $this->assertSame($from, $history->from_status);
        $this->assertSame($to, $history->to_status);
        $this->assertEquals($actorId, $history->actor_id);
        $this->assertSame($reason, $history->reason);
        $this->assertSame($revision, $history->revision_no);
        $this->assertNotNull($history->created_at);
        $this->assertArrayNotHasKey('updated_at', $history->getAttributes());
    }

    private function assertDeniedUnchanged(MaterialPurchaseRequest $pr, callable $operation): void
    {
        $this->assertFailureUnchanged($pr, AuthorizationException::class, $operation);
    }

    private function assertFailureUnchanged(MaterialPurchaseRequest $pr, string $exceptionClass, callable $operation, ?string $message = null): void
    {
        $before = $pr->fresh()->getRawOriginal();
        $details = $pr->details()->orderBy('id')->get()->toArray();
        $histories = $pr->histories()->get()->toArray();
        try {
            $operation();
            $this->fail('Operasi harus ditolak dengan '.$exceptionClass);
        } catch (\Exception $e) {
            $this->assertInstanceOf($exceptionClass, $e);
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }
        $this->assertSame($before, $pr->fresh()->getRawOriginal());
        $this->assertSame($details, $pr->details()->orderBy('id')->get()->toArray());
        $this->assertSame($histories, $pr->histories()->get()->toArray());
    }
}
