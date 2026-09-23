<?php

namespace Tests\Feature;

use App\Modules\Customs\Jobs\SubmitCustomsDocumentJob;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\CeisaH2HClient;
use App\Modules\Customs\Services\CeisaSignatureService;
use App\Modules\Customs\Services\CustomsDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task E (Fase 2): client CEISA H2H sungguhan.
 *
 * PENTING: SEMUA test di sini memakai Http::fake() — TIDAK ADA request sungguhan
 * ke apis-sandbox.beacukai.go.id atau domain CEISA manapun. Test ini menguji
 * perilaku client TERHADAP Http::fake(), bukan konektivitas ke server CEISA.
 */
class CeisaH2HClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default: modul nonaktif (sesuai default config).
        // CATATAN: Http::fake() TIDAK dipanggil di setUp — di Laravel, pemanggilan
        // fake() berikutnya tidak menimpa stub catch-all pertama, sehingga setiap
        // test memanggil Http::fake() sendiri sesuai stub yang dibutuhkan.
        config(['customs.enabled' => false]);
    }

    /**
     * Regresi untuk gap yang ditemukan di Task D: guard config('customs.enabled')
     * benar-benar memblokir submit sebelum ada request terkirim.
     */
    public function test_submit_throws_when_module_disabled(): void
    {
        Http::fake();

        $client = new CeisaH2HClient(new CeisaSignatureService());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak aktif');

        $client->submit('PIB', ['foo' => 'bar'], 'corr-1');
    }

    /**
     * Mengaktifkan modul HANYA di dalam test ini (config runtime; file config/
     * .env tetap false). Request diverifikasi terhadap Http::fake().
     */
    public function test_submit_sends_correct_headers_and_path_when_enabled(): void
    {
        // Pin base URL eksplisit supaya test tidak tergantung isi .env lokal.
        config(['customs.endpoints.sandbox' => 'https://apis-sandbox.beacukai.go.id']);
        $baseUrl = config('customs.endpoints.sandbox');

        // Satu pemanggilan fake() dengan dua pola: stub target + catch-all 599
        // sebagai tripwire — kalau URL request tidak match pola target, response
        // 599 membuat assert gagal keras dan TIDAK PERNAH ada request sungguhan.
        Http::fake([
            $baseUrl.'/*' => Http::response(['status' => 'RECEIVED'], 200),
            '*' => Http::response(['unexpected_stub' => true], 599),
        ]);

        config([
            'customs.enabled' => true,
            'customs.signing.client_id' => 'test-client-id',
            'customs.signing.client_secret' => 'test-secret',
            'customs.signing.access_token' => 'test-token',
        ]);

        $client = new CeisaH2HClient(new CeisaSignatureService());
        $response = $client->submit('PIB', ['foo' => 'bar'], 'corr-42');

        $this->assertSame(200, $response['http_status']);
        $this->assertSame(['status' => 'RECEIVED'], $response['body']);

        Http::assertSent(function ($request) use ($baseUrl) {
            $path = config('customs.endpoints.paths.PIB');

            return $request->method() === 'POST'
                && $request->url() === $baseUrl.$path
                && $request->hasHeader('beacukai-signature')
                && $request->header('client-id')[0] === 'test-client-id'
                && $request->hasHeader('timestamp')
                && $request->header('Authorization')[0] === 'Bearer test-token'
                && $request->header('X-Correlation-Id')[0] === 'corr-42'
                && $request['foo'] === 'bar';
        });
    }

    /**
     * Regresi khusus bug lama di Task D: dulu callCeisaApi() SELALU mengembalikan
     * sukses palsu tanpa mengecek config('customs.enabled'). Sekarang job harus
     * gagal dengan pesan jelas, status dokumen TIDAK berubah jadi sukses, dan
     * tidak ada HTTP request terkirim.
     */
    public function test_submit_job_does_not_fake_success_when_disabled(): void
    {
        config(['customs.signing.client_id' => 'test-client-id']);
        config(['customs.signing.client_secret' => 'test-secret']);
        config(['customs.signing.access_token' => 'test-token']);

        Http::fake();

        $document = CustomsDocument::create([
            'document_type' => 'PIB',
            'internal_number' => 'PIB/2026/09/JOBTEST-'.uniqid(),
            'source_type' => 'purchase_orders',
            'source_id' => random_int(1, 999999),
            'status' => CustomsDocument::STATUS_QUEUED,
            'environment' => 'sandbox',
        ]);

        // withFakeQueueInteractions(): tanpa ini, fail() di luar konteks queue
        // TIDAK melakukan apa-apa (InteractsWithQueue::fail hanya bekerja jika
        // $this->job terisi) — dengan ini kita bisa assert job benar-benar gagal.
        $job = (new SubmitCustomsDocumentJob($document->id))->withFakeQueueInteractions();
        $job->handle(app(CustomsDocumentService::class));

        // Job harus gagal (bukan pura-pura sukses) dengan pesan yang jelas.
        $job->assertFailedWith(
            new \RuntimeException('Modul Customs (CEISA H2H) tidak aktif. Set CEISA_ENABLED=true di .env untuk mengaktifkan (hanya untuk testing manual terverifikasi).')
        );

        // Status dokumen TIDAK boleh berubah jadi sukses / SPPB_ISSUED.
        $this->assertDatabaseHas('cst_customs_documents', [
            'id' => $document->id,
            'status' => CustomsDocument::STATUS_QUEUED,
        ]);

        // Audit error module_disabled tercatat di timeline log dokumen.
        $this->assertDatabaseHas('cst_customs_document_logs', [
            'customs_document_id' => $document->id,
            'event_type' => 'ERROR',
        ]);

        // TIDAK ADA HTTP request terkirim.
        Http::assertNothingSent();
    }
}