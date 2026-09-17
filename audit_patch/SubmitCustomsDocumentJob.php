<?php

namespace App\Modules\Customs\Jobs;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Support\CustomsAuditLogger;
use App\Modules\Customs\Services\CustomsDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitCustomsDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public int $customsDocumentId;

    public function __construct(int $customsDocumentId)
    {
        $this->customsDocumentId = $customsDocumentId;
        $this->onQueue('customs');
    }

    public function handle(CustomsDocumentService $documentService): void
    {
        $document = CustomsDocument::with('details')->find($this->customsDocumentId);

        if (! $document) {
            Log::error("CustomsDocument #{$this->customsDocumentId} tidak ditemukan.", [
                'job' => self::class,
            ]);
            return;
        }

        if (! $document->canBeSubmitted()) {
            Log::warning("Dokumen {$document->internal_number} tidak dapat di-submit. Status: {$document->status}");
            return;
        }

        $currentHash = hash('sha256', $this->buildPayload($document));
        if ($document->payload_hash && $document->payload_hash !== $currentHash) {
            Log::warning("Payload dokumen {$document->internal_number} telah berubah.");
            return;
        }

        $document->payload_hash = $currentHash;
        $document->save();

        // FIX (T7): SEBELUMNYA generateCorrelationId() dipanggil inline dan hasilnya langsung
        // dilempar sebagai argumen ke logOutboundRequest() TANPA PERNAH disimpan ke
        // $this->correlationId. Property itu dideklarasikan `private string $correlationId;`
        // (typed, tanpa default) lalu diakses lagi di bawah (logInboundResponse, catch block)
        // -> PHP 8 melempar "Error: Typed property ... must not be accessed before
        // initialization" (fatal error) begitu job ini dijalankan. Sekarang di-assign eksplisit
        // sekali di awal supaya konsisten dipakai di seluruh method handle().
        $this->correlationId = $this->generateCorrelationId();

        $payload = $this->buildPayload($document);
        // FIX (T8): method logOutboundRequest()/logInboundResponse() TIDAK ADA di
        // CustomsAuditLogger — method yang benar-benar ada bernama logRequest()/logResponse().
        // Panggilan lama akan fatal error "Call to undefined method" sebelum sempat mengirim
        // apapun ke CEISA, jadi audit trail (kewajiban jejak kepabeanan) juga tidak pernah
        // tercatat. Diperbaiki memakai nama & urutan parameter sesuai signature asli:
        // logRequest(documentId, eventType, payload, httpStatus, correlationId, actorUserId).
        CustomsAuditLogger::logRequest(
            $document->id,
            'SUBMIT',
            $payload,
            null,
            $this->correlationId,
            $document->created_by
        );

        try {
            $startTime = microtime(true);
            $response = $this->callCeisaApi($document, $payload);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            // FIX (T8): logResponse(documentId, eventType, response, httpStatus, correlationId, latencyMs)
            CustomsAuditLogger::logResponse(
                $document->id,
                'SUBMIT',
                $response['body'] ?? [],
                $response['http_status'] ?? null,
                $this->correlationId,
                $latencyMs
            );

            $documentService->updateStatusFromResponse(
                $document->id,
                $response['ceisa_status'],
                $response['body'],
                $response['nomor_aju'] ?? null,
                $response['nomor_pendaftaran'] ?? null
            );

        } catch (\Exception $e) {
            CustomsAuditLogger::logError(
                $document->id,
                $e->getMessage(),
                $this->correlationId,
                ['exception_class' => get_class($e)]
            );

            $document->last_error_code = $e->getCode() ?: 'UNKNOWN_ERROR';
            $document->last_error_message = $e->getMessage();
            $document->save();

            throw $e;
        }
    }

    private function buildPayload(CustomsDocument $document): array
    {
        return [
            'document_type' => $document->document_type,
            'internal_number' => $document->internal_number,
            'kode_kantor' => $document->kode_kantor,
        ];
    }

    /**
     * FIX (T9): SEBELUMNYA method ini SELALU mengembalikan sukses palsu
     * (status 'SUBMIT_SUCCESS' + nomor AJU acak dari uniqid()) tanpa pernah memanggil
     * CeisaH2HClient yang sebenarnya sudah dibangun lengkap (HTTP client + HMAC signer).
     * Akibatnya setiap dokumen PIB/PEB yang "disubmit" akan selalu tampak berhasil di sistem
     * padahal TIDAK PERNAH benar-benar terkirim ke Bea Cukai -- nomor AJU yang tersimpan
     * adalah data palsu, bukan nomor resmi dari DJBC. Ini berisiko fatal jika dianggap data
     * sah untuk keperluan legal/audit kepabeanan.
     *
     * Sekarang memanggil CeisaH2HClient::submit() yang sesungguhnya. Client tersebut punya
     * guard bawaan: melempar RuntimeException jika config('customs.enabled') masih false
     * (default aman selama endpoint resmi DJBC belum diterima/dikonfigurasi). Guard itu
     * SENGAJA TIDAK di-bypass di sini -- exception dibiarkan naik ke handle() supaya job
     * gagal secara jujur (tercatat sebagai error, di-retry sesuai $tries/$backoff) daripada
     * dipalsukan sebagai sukses.
     */
    private function callCeisaApi(CustomsDocument $document, array $payload): array
    {
        /** @var \App\Modules\Customs\Services\CeisaH2HClient $client */
        $client = app(\App\Modules\Customs\Services\CeisaH2HClient::class);

        $response = $client->submit($document->document_type, $payload, $this->correlationId);
        $body = $response['body'] ?? [];

        return [
            'http_status' => $response['http_status'],
            'body' => $body,
            'ceisa_status' => $body['status'] ?? 'UNDER_REVIEW',
            'nomor_aju' => $body['nomor_aju'] ?? null,
            'nomor_pendaftaran' => $body['nomor_pendaftaran'] ?? null,
        ];
    }

    private string $correlationId;

    private function generateCorrelationId(): string
    {
        return 'CEISA_' . uniqid() . '_' . time();
    }
}

