<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\KepabeanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KepabeanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customs.enabled' => false, 'customs.reports_enabled' => true]);
        Http::preventStrayRequests();
    }

    private function payload(): array
    {
        return ['document_type' => 'BC 4.0', 'nomor_aju' => '00004001234520260922000001',
            'kode_kantor' => '012345', 'currency' => 'IDR', 'exchange_rate' => 1,
            'details' => [['hs_code' => '12345678', 'deskripsi_barang' => 'Kain', 'qty' => 5, 'satuan' => 'MTR', 'berat_bersih' => 2, 'nilai' => 5000]]];
    }

    public function test_guests_cannot_access_local_recording(): void
    {
        $this->get(route('kepabean.dashboard'))->assertRedirect(route('login'));
        $this->get(route('kepabean.documents'))->assertRedirect(route('login'));
        $this->post(route('kepabean.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('cst_customs_documents', 0);
    }

    public function test_draft_create_preview_update_and_archive_without_h2h(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('kepabean.create'))->assertOk();
        $this->post(route('kepabean.store'), $this->payload())->assertRedirect();
        $document = CustomsDocument::firstOrFail();
        $this->assertSame('DRAFT', $document->status);
        $this->assertSame('5000.00', $document->total_value);
        $this->assertSame(1, $document->details()->count());
        $this->get(route('kepabean.show', $document))->assertOk()->assertSee('Kain');
        $this->get(route('kepabean.edit', $document))->assertOk();
        $payload = $this->payload();
        $payload['details'][0]['nilai'] = 7000;
        $this->put(route('kepabean.update', $document), $payload)->assertRedirect(route('kepabean.show', $document));
        $this->assertSame('7000.00', $document->fresh()->total_value);
        $this->assertSame(1, $document->details()->count());
        // A second manual draft must not collide with the source unique constraint.
        $this->post(route('kepabean.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseCount('cst_customs_documents', 2);
        $this->delete(route('kepabean.destroy', $document))->assertRedirect(route('kepabean.documents'));
        $this->assertSame('VOIDED', $document->fresh()->status);
        $this->assertSame(1, $document->details()->count());
        $this->assertSame(3, $document->statusHistory()->count());
        $this->get(route('kepabean.documents', ['status' => 'VOIDED']))->assertOk()->assertSee($document->internal_number);
        Http::assertNothingSent();
    }

    public function test_submitted_documents_cannot_be_edited_or_archived(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $document = app(KepabeanService::class)->save($this->payload(), null, $user->id);
        $document->update(['status' => 'SUBMITTED']);
        $this->get(route('kepabean.edit', $document))->assertStatus(409);
        $this->put(route('kepabean.update', $document), $this->payload())->assertSessionHas('error');
        $this->delete(route('kepabean.destroy', $document))->assertSessionHas('error');
        $this->assertSame('SUBMITTED', $document->fresh()->status);
    }

    public function test_dashboard_uses_real_monthly_counts_and_submission_dates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(KepabeanService::class);
        $old = $service->save($this->payload(), null, $user->id);
        $old->forceFill(['created_at' => '2026-09-22 10:00:00', 'submitted_at' => '2026-10-02 10:00:00', 'status' => 'SUBMITTED'])->save();
        $draft = $service->save($this->payload(), null, $user->id);
        $draft->forceFill(['created_at' => '2026-10-03 10:00:00'])->save();
        $void = $service->save($this->payload(), null, $user->id);
        $void->forceFill(['created_at' => '2026-10-04 10:00:00', 'status' => 'VOIDED'])->save();
        $this->get(route('kepabean.dashboard', ['month' => '2026-10']))->assertOk()
            ->assertViewHas('count', 1)->assertViewHas('submitted', 1)->assertViewHas('drafts', 1)
            ->assertViewHas('total', 2)->assertViewHas('previous', 1)
            ->assertViewHas('trend', fn ($trend) => $trend['2026-10-03'] === 1 && count($trend) === 31)
            ->assertViewHas('types', fn ($types) => $types['BC 4.0'] === 1);
        $this->get(route('kepabean.dashboard', ['month' => 'bad']))->assertSessionHasErrors('month');
    }

    public function test_search_pagination_and_sidebar_are_available_without_h2h(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $service = app(KepabeanService::class);
        for ($i = 0; $i < 16; $i++) {
            $payload = $this->payload();
            $payload['nomor_aju'] = 'AJU-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $service->save($payload, null, $user->id);
        }
        $this->get(route('kepabean.documents'))->assertOk()->assertViewHas('documents', fn ($docs) => $docs->total() === 16 && $docs->count() === 15)
            ->assertSee(route('kepabean.dashboard'))->assertSee(route('kepabean.documents'));
        $this->get(route('kepabean.documents', ['q' => 'AJU-0002']))->assertOk()
            ->assertViewHas('documents', fn ($docs) => $docs->total() === 1)->assertSee('AJU-0002')->assertDontSee('AJU-0003');
        $this->assertFalse(config('customs.enabled'));
        Http::assertNothingSent();
    }

    public function test_invalid_goods_are_rejected_without_partial_document(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $payload['details'][0]['qty'] = -1;
        $this->post(route('kepabean.store'), $payload)->assertSessionHasErrors('details.0.qty');
        $this->assertDatabaseCount('cst_customs_documents', 0);
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $this->assertTrue(app('translator')->hasForLocale('kepabean.dashboard', $locale));
        }
    }
}