<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialRequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private MaterialProcurementService $service;

    private int $yarnId;

    private User $creator;

    private User $approver;

    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MaterialProcurementService::class);
        $this->yarnId = Yarn::create(['yarn_code' => 'YARN-AUTH', 'yarn_type' => 'Cotton', 'unit' => 'KGS'])->id;
        $this->creator = User::factory()->create(['name' => 'Pembuat PR Berizin']);
        $this->approver = User::factory()->create(['name' => 'Penyetuju PR Berizin']);
        $this->outsider = User::factory()->create(['name' => 'User Tanpa Izin']);
        // FIX: izin eksplisit memakai allowlist config default kosong (deny), tanpa pemberian role otomatis.
        config([
            'platform.pr_view_user_ids' => [],
            'platform.pr_create_user_ids' => [(string) $this->creator->id],
            'platform.pr_approve_user_ids' => [(string) $this->approver->id],
        ]);
    }

    public function test_user_without_permission_gets_403_on_every_pr_mutation(): void
    {
        $draft = $this->draftRequest($this->creator);
        $submitted = $this->submittedRequest($this->creator);

        $this->actingAs($this->outsider)->get(route('mfg.material-requests.create'))->assertForbidden();
        $this->actingAs($this->outsider)
            ->post(route('mfg.material-requests.store'), $this->payload())
            ->assertForbidden();
        $this->assertDatabaseCount('mfg_material_purchase_requests', 2);

        $this->actingAs($this->outsider)
            ->post(route('mfg.material-requests.submit', $draft->id))
            ->assertForbidden();
        $this->assertSame(MaterialPurchaseRequest::DRAFT, $draft->fresh()->approval_status);
        $this->assertNull($draft->fresh()->submitted_by);

        $this->actingAs($this->outsider)
            ->post(route('mfg.material-requests.approve', $submitted->id))
            ->assertForbidden();
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $submitted->fresh()->approval_status);
        $this->assertNull($submitted->fresh()->approved_by);

        $this->actingAs($this->outsider)
            ->post(route('mfg.material-requests.reject', $submitted->id), ['rejection_reason' => 'Alasan uji otorisasi'])
            ->assertForbidden();
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $submitted->fresh()->approval_status);
        $this->assertNull($submitted->fresh()->rejected_by);
    }

    public function test_read_access_requires_one_of_the_explicit_allowlists(): void
    {
        $draft = $this->draftRequest($this->creator);
        $this->actingAs($this->outsider)->get(route('mfg.material-requests.index'))->assertForbidden();
        $this->get(route('mfg.material-requests.show', $draft->id))->assertForbidden();
        config(['platform.pr_view_user_ids' => [(string) $this->outsider->id]]);
        $this->get(route('mfg.material-requests.index'))->assertOk();
        $this->get(route('mfg.material-requests.show', $draft->id))->assertOk();
        $this->actingAs($this->creator)->get(route('mfg.material-requests.index'))->assertOk();
        $this->actingAs($this->approver)->get(route('mfg.material-requests.index'))->assertOk();
    }

    public function test_creator_cannot_approve_own_request_even_with_approve_permission(): void
    {
        // FIX: pembuat sengaja diberi izin approve agar penolakan murni karena segregation of duties.
        config(['platform.pr_approve_user_ids' => [(string) $this->creator->id]]);
        $submitted = $this->submittedRequest($this->creator);

        $this->actingAs($this->creator)
            ->post(route('mfg.material-requests.approve', $submitted->id))
            ->assertForbidden();
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $submitted->fresh()->approval_status);
        $this->assertNull($submitted->fresh()->approved_by);

        try {
            $this->service->approveRequest($submitted->id, $this->creator->id);
            $this->fail('Service harus menolak self-approval.');
        } catch (AuthorizationException) {
            // FIX: diterima — lapisan service ikut menegakkan SoD.
        }
    }

    public function test_other_user_cannot_submit_someone_else_draft(): void
    {
        // FIX: outsider berizin create sehingga penolakan murni karena ownership, bukan izin create.
        config(['platform.pr_create_user_ids' => [(string) $this->creator->id, (string) $this->outsider->id]]);
        $draft = $this->draftRequest($this->creator);

        $this->actingAs($this->outsider)
            ->post(route('mfg.material-requests.submit', $draft->id))
            ->assertForbidden();
        $this->assertSame(MaterialPurchaseRequest::DRAFT, $draft->fresh()->approval_status);
        $this->assertNull($draft->fresh()->submitted_by);
    }

    public function test_authorized_non_creator_approver_approves_request(): void
    {
        $submitted = $this->submittedRequest($this->creator);

        $this->actingAs($this->approver)
            ->post(route('mfg.material-requests.approve', $submitted->id))
            ->assertRedirect();

        $fresh = $submitted->fresh();
        $this->assertSame(MaterialPurchaseRequest::APPROVED, $fresh->approval_status);
        $this->assertSame($this->approver->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_reject_without_reason_is_rejected(): void
    {
        $submitted = $this->submittedRequest($this->creator);

        $this->actingAs($this->approver)
            ->post(route('mfg.material-requests.reject', $submitted->id))
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $submitted->fresh()->approval_status);
        $this->assertNull($submitted->fresh()->rejected_by);

        $this->actingAs($this->approver)
            ->post(route('mfg.material-requests.reject', $submitted->id), ['rejection_reason' => 'Stok mencukupi'])
            ->assertRedirect();
        $this->assertSame(MaterialPurchaseRequest::REJECTED, $submitted->fresh()->approval_status);
        $this->assertSame($this->approver->id, $submitted->fresh()->rejected_by);
    }

    public function test_service_denies_unauthorized_callers_even_when_controller_is_bypassed(): void
    {
        $submitted = $this->submittedRequest($this->creator);
        $draft = $this->draftRequest($this->creator);

        try {
            $this->service->approveRequest($submitted->id, $this->outsider->id);
            $this->fail('Service harus menolak approver tanpa izin.');
        } catch (AuthorizationException) {
            // FIX: diterima — controller dilewati tetap ditolak.
        }
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $submitted->fresh()->approval_status);

        try {
            $this->service->submitRequest($draft->id, $this->outsider->id);
            $this->fail('Service harus menolak submit oleh non-pembuat.');
        } catch (AuthorizationException) {
            // FIX: diterima — ownership berlaku di service.
        }
        $this->assertSame(MaterialPurchaseRequest::DRAFT, $draft->fresh()->approval_status);

        try {
            $this->service->createRequest(['request_date' => '2026-10-05', 'created_by' => $this->outsider->id], [$this->item(2)]);
            $this->fail('Service harus menolak create tanpa izin.');
        } catch (AuthorizationException) {
            // FIX: diterima — allowlist create berlaku di service.
        }
        $this->assertDatabaseCount('mfg_material_purchase_requests', 2);
    }

    public function test_admin_role_without_explicit_permission_is_denied(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $submitted = $this->submittedRequest($this->creator);

        $this->actingAs($admin)->post(route('mfg.material-requests.store'), $this->payload())->assertForbidden();
        $this->actingAs($admin)->post(route('mfg.material-requests.approve', $submitted->id))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('mfg.material-requests.reject', $submitted->id), ['rejection_reason' => 'Alasan admin uji'])
            ->assertForbidden();

        $fresh = $submitted->fresh();
        $this->assertSame(MaterialPurchaseRequest::SUBMITTED, $fresh->approval_status);
        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->rejected_by);
        $this->assertDatabaseCount('mfg_material_purchase_requests', 1);

        try {
            $this->service->approveRequest($submitted->id, $admin->id);
            $this->fail('Service harus menolak ADMIN tanpa allowlist eksplisit.');
        } catch (AuthorizationException) {
            // FIX: diterima — string ADMIN tidak menjadi bypass.
        }
    }

    // FIX: PR DRAFT milik pembuat tertentu untuk skenario ownership.
    private function draftRequest(User $owner): MaterialPurchaseRequest
    {
        return $this->service->createRequest([
            'request_date' => '2026-10-05',
            'created_by' => $owner->id,
            'remarks' => 'PR untuk uji otorisasi',
        ], [$this->item(5)]);
    }

    // FIX: PR SUBMITTED milik pembuat, disubmit oleh pembuatnya sendiri.
    private function submittedRequest(User $owner): MaterialPurchaseRequest
    {
        $request = $this->draftRequest($owner);
        $this->service->submitRequest($request->id, $owner->id);

        return $request;
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarnId, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 10000];
    }

    private function payload(): array
    {
        return ['request_date' => '2026-10-05', 'remarks' => 'PR baru dari uji otorisasi', 'items' => [$this->item(3)]];
    }
}
