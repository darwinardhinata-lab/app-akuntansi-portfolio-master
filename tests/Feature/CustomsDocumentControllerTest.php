<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Modules\Customs\Jobs\SubmitCustomsDocumentJob;
use App\Modules\Customs\Models\CustomsDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * FIX (Fase 1-B / Task L): test yang benar-benar menembus Controller/HTTP.
 *
 * Semua test memakai $this->get()/post() terhadap route (bukan memanggil service
 * langsung), sehingga regresi bug C1/C7/H2 tertangkap di level HTTP.
 *
 * Catatan: routes/customs.php hanya mendaftarkan route ketika
 * config('customs.enabled') bernilai true SAAT file route di-load. Karena route
 * di-load pada boot aplikasi (config test default: false), route modul ini
 * didaftarkan manual di setUp() dengan meng-require file routes/customs.php
 * (dijaga agar tidak dobel). Setelah itu config dikembalikan ke false —
 * sehingga TIDAK ada panggilan HTTP keluar selama test (di-assert juga via
 * Http::assertNothingSent()).
 */
class CustomsDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL lokal (mis. http://localhost/app-akuntansi-portfolio-master/public)
        // membuat route()/url() menghasilkan URL absolut ber-subfolder sehingga semua
        // request test 404. Paksa root URL generator ke host bersih.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        Http::fake();

        if (! Route::has('customs.index')) {
            // Daftarkan route modul customs (butuh config enabled=true saat load).
            config(['customs.enabled' => true]);
            require base_path('routes/customs.php');
        }

        // Kembalikan ke default: modul CEISA nonaktif.
        config(['customs.enabled' => false]);
    }

    // PART2

    /**
     * Buat dokumen customs langsung via model (tanpa factory — modul ini
     * belum punya factory; pembuatan via service diuji di Foundation test).
     * source_id dibuat unik per pemanggilan karena tabel punya UNIQUE
     * (source_type, source_id, document_type).
     */
    private static int $docSeq = 0;

    private function makeDocument(string $status = 'DRAFT'): CustomsDocument
    {
        return CustomsDocument::create([
            'document_type' => 'PIB',
            'internal_number' => 'PIB/2026/09/T' . uniqid(),
            'source_type' => 'purchase_orders',
            'source_id' => ++self::$docSeq,
            'status' => $status,
            'environment' => 'sandbox',
        ]);
    }

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_create_draft_via_http_route_succeeds(): void
    {
        config(['customs.enabled' => false]);
        $this->actingUser();

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'transaction_date' => now()->toDateString(),
            'contact_name' => 'Supplier Test',
            'location_name' => 'Gudang Utama',
            'status' => 'completed',
            'sub_total' => 1000000,
            'grand_total' => 1100000,
        ]);

        $response = $this->get(route('customs.create', ['sourceType' => 'purchase_order', 'sourceId' => $po->id]));

        // Regresi C1: kalau urutan argumen createDraft() terbalik lagi,
        // test ini gagal (redirect atau DB row tidak sesuai).
        $newDocumentId = CustomsDocument::latest('id')->first()->id;
        $response->assertRedirect(route('customs.edit', $newDocumentId));

        $this->assertDatabaseHas('cst_customs_documents', [
            'source_type' => 'purchase_order',
            'source_id' => $po->id,
            'document_type' => 'PIB',
            'status' => 'DRAFT',
        ]);

        Http::assertNothingSent();
    }

    public function test_submit_via_http_route_dispatches_job(): void
    {
        config(['customs.enabled' => false]);
        Queue::fake();
        $this->actingUser();

        $document = $this->makeDocument('DRAFT');

        $response = $this->post(route('customs.submit', $document->id));

        $response->assertRedirect(route('customs.show', $document->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $document->id,
            'status' => 'QUEUED',
        ]);

        Queue::assertPushed(SubmitCustomsDocumentJob::class, function ($job) use ($document) {
            return $job->customsDocumentId === $document->id;
        });

        Http::assertNothingSent();
    }

    // PART3

    public function test_submit_blocked_when_status_not_submittable(): void
    {
        config(['customs.enabled' => false]);
        Queue::fake();
        $this->actingUser();

        $document = $this->makeDocument('SUBMITTED');

        $response = $this->post(route('customs.submit', $document->id));

        // Tidak boleh 500 — controller menangkap exception dan kembali dengan pesan error.
        $response->assertStatus(302);
        $response->assertSessionHas('error', function ($error) {
            return str_contains($error, 'tidak dapat diajukan');
        });

        // Status TIDAK berubah.
        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $document->id,
            'status' => 'SUBMITTED',
        ]);

        Queue::assertNotPushed(SubmitCustomsDocumentJob::class);

        Http::assertNothingSent();
    }

    public function test_retry_only_allowed_from_need_correction_or_rejected(): void
    {
        config(['customs.enabled' => false]);
        Queue::fake();
        $this->actingUser();

        // Dari DRAFT: harus DITOLAK.
        $draft = $this->makeDocument('DRAFT');
        $this->post(route('customs.retry', $draft->id))
            ->assertStatus(302)
            ->assertSessionHas('error', function ($error) {
                return str_contains($error, 'tidak dapat di-retry');
            });

        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $draft->id,
            'status' => 'DRAFT',
            'retry_count' => 0,
        ]);

        // Dari NEED_CORRECTION: harus DIBOLEHKAN -> QUEUED + retry_count 1.
        $correction = $this->makeDocument('NEED_CORRECTION');
        $this->post(route('customs.retry', $correction->id))
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $correction->id,
            'status' => 'QUEUED',
            'retry_count' => 1,
        ]);

        Queue::assertPushed(SubmitCustomsDocumentJob::class);

        Http::assertNothingSent();
    }

    public function test_void_blocked_for_submitted_document(): void
    {
        config(['customs.enabled' => false]);
        Queue::fake();
        $this->actingUser();

        $document = $this->makeDocument('SUBMITTED');

        $response = $this->post(route('customs.void', $document->id));

        $response->assertStatus(302);
        $response->assertSessionHas('error', function ($error) {
            return str_contains($error, 'tidak dapat dibatalkan');
        });

        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $document->id,
            'status' => 'SUBMITTED',
        ]);

        Http::assertNothingSent();
    }

    public function test_show_and_print_and_edit_views_render_without_error(): void
    {
        config(['customs.enabled' => false]);
        $this->actingUser();

        $document = $this->makeDocument('DRAFT');

        // Regresi C7: kalau view show/print/edit hilang atau error, test ini gagal.
        $this->get(route('customs.show', $document->id))->assertOk();
        $this->get(route('customs.print', $document->id))->assertOk();
        $this->get(route('customs.edit', $document->id))->assertOk();

        // Edit untuk dokumen non-DRAFT harus redirect ke show.
        $submitted = $this->makeDocument('SUBMITTED');
        $this->get(route('customs.edit', $submitted->id))
            ->assertRedirect(route('customs.show', $submitted->id));

        Http::assertNothingSent();
    }

    public function test_webhook_route_returns_not_implemented_when_enabled(): void
    {
        // Khusus test ini modul di-enable (boleh — hanya config runtime di test;
        // file config/.env/.env.example default TETAP false/kosong).
        config(['customs.enabled' => true]);

        $response = $this->post('/webhook/customs/ceisa', [
            'document_type' => 'PIB',
            'status' => 'APPROVED',
        ]);

        // Regresi H2: webhook TIDAK boleh pura-pura sukses (200 OK tanpa proses).
        $response->assertStatus(501);
        $response->assertJsonPath('status', 'NOT_IMPLEMENTED');

        Http::assertNothingSent();
    }
}
