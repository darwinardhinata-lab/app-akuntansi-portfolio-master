<?php

namespace Tests\Feature;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Models\CustomsDocumentLog;
use App\Modules\Customs\Services\CeisaH2HClient;
use App\Modules\Customs\Services\CeisaSignatureService;
use App\Modules\Customs\Services\CustomsDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomsDocumentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customs.enabled' => false]);
        config(['customs.signing.api_secret' => 'test-secret']);
    }

    public function test_create_draft_produces_draft_status_and_internal_number(): void
    {
        config(['customs.enabled' => true]);
        Http::fake();

        $service = app(CustomsDocumentService::class);
        $document = $service->createDraft('purchase_orders', 1, 'PIB', null);

        $this->assertNotNull($document->id);
        $this->assertEquals('DRAFT', $document->status);
        $this->assertNotNull($document->internal_number);
        $this->assertStringStartsWith('PIB/', $document->internal_number);
        $this->assertEquals('purchase_orders', $document->source_type);
        $this->assertEquals(1, $document->source_id);
        $this->assertEquals('PIB', $document->document_type);

        Http::assertNothingSent();
    }

    public function test_duplicate_draft_throws_exception(): void
    {
        config(['customs.enabled' => true]);
        Http::fake();

        $service = app(CustomsDocumentService::class);
        $service->createDraft('purchase_orders', 1, 'PIB', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ada');

        $service->createDraft('purchase_orders', 1, 'PIB', null);

        Http::assertNothingSent();
    }

    public function test_submit_throws_exception_when_disabled(): void
    {
        config(['customs.enabled' => false]);
        Http::fake();

        $signer = new CeisaSignatureService();
        $client = new CeisaH2HClient($signer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak diaktifkan');

        $client->submit('PIB', ['test' => 'data'], 'corr-123');

        Http::assertNothingSent();
    }

    public function test_check_status_throws_exception_when_disabled(): void
    {
        config(['customs.enabled' => false]);
        Http::fake();

        $signer = new CeisaSignatureService();
        $client = new CeisaH2HClient($signer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak diaktifkan');

        $client->checkStatus('AJU-123');

        Http::assertNothingSent();
    }

    public function test_customs_document_log_rejects_update(): void
    {
        // Buat dokumen terlebih dahulu agar FK constraint terpenuhi
        $document = CustomsDocument::create([
            'document_type' => 'PIB',
            'internal_number' => 'PIB/2026/09/00001',
            'source_type' => 'purchase_orders',
            'source_id' => 1,
            'status' => 'DRAFT',
            'environment' => 'sandbox',
        ]);

        $log = CustomsDocumentLog::create([
            'customs_document_id' => $document->id,
            'direction' => 'OUTBOUND',
            'event_type' => 'TEST',
            'request_payload' => json_encode(['test' => 'data']),
            'correlation_id' => 'test-corr-123',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tidak boleh diupdate');

        $log->update(['event_type' => 'UPDATED']);
    }

    public function test_customs_document_log_rejects_delete(): void
    {
        // Buat dokumen terlebih dahulu agar FK constraint terpenuhi
        $document = CustomsDocument::create([
            'document_type' => 'PIB',
            'internal_number' => 'PIB/2026/09/00002',
            'source_type' => 'purchase_orders',
            'source_id' => 1,
            'status' => 'DRAFT',
            'environment' => 'sandbox',
        ]);

        $log = CustomsDocumentLog::create([
            'customs_document_id' => $document->id,
            'direction' => 'OUTBOUND',
            'event_type' => 'TEST',
            'request_payload' => json_encode(['test' => 'data']),
            'correlation_id' => 'test-corr-456',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tidak boleh dihapus');

        $log->delete();
    }

    public function test_signature_throws_exception_when_no_secret(): void
    {
        config(['customs.enabled' => true]);
        config(['customs.signing.api_secret' => null]);

        $signer = new CeisaSignatureService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CEISA_API_SECRET tidak diatur');

        $signer->sign(['test' => 'data']);
    }

    public function test_signature_produces_valid_hmac(): void
    {
        config(['customs.enabled' => true]);
        config(['customs.signing.method' => 'hmac']);
        config(['customs.signing.api_secret' => 'test-secret-key']);

        $signer = new CeisaSignatureService();
        $result = $signer->sign(['document_type' => 'PIB', 'value' => 1000]);

        $this->assertArrayHasKey('signature', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertIsString($result['signature']);
        $this->assertNotEmpty($result['signature']);

        $payloadJson = $result['body'];
        $timestamp = $result['timestamp'] ?? time();
        $expectedSignature = hash_hmac('sha256', $payloadJson . $timestamp, 'test-secret-key');
        $this->assertEquals($expectedSignature, $result['signature']);
    }
}
