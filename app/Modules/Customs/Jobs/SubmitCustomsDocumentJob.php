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

        // FIX (C2/Fase 1-B): guard status di JOB eksplisit DRAFT/QUEUED. canBeSubmitted()
        // di model sekarang hanya mengizinkan DRAFT/NEED_CORRECTION (guard untuk service
        // submit()), sedangkan job ini justru memproses dokumen yang berstatus QUEUED.
        if (! in_array($document->status, [CustomsDocument::STATUS_DRAFT, CustomsDocument::STATUS_QUEUED], true)) {
            Log::warning("Dokumen {$document->internal_number} tidak dapat di-submit. Status: {$document->status}");
            return;
        }

        // FIX (C5): hash() butuh string, bukan array.
        $currentHash = hash('sha256', json_encode($this->buildPayload($document)));
        if ($document->payload_hash && $document->payload_hash !== $currentHash) {
            Log::warning("Payload dokumen {$document->internal_number} telah berubah.");
            return;
        }

        $document->payload_hash = $currentHash;
        $document->save();

        $payload = $this->buildPayload($document);

        // FIX (C6 + C4): correlationId disimpan ke property SEBELUM dipakai, dan
        // method logger yang benar adalah logRequest() (bukan logOutboundRequest()).
        $this->correlationId = $this->generateCorrelationId();

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
            $latencyMs = (microtime(true) - $startTime) * 1000;

            // FIX (C4): method yang benar adalah logResponse() dengan urutan parameter
            // (documentId, eventType, response, httpStatus, correlationId, latencyMs).
            CustomsAuditLogger::logResponse(
                $document->id,
                'SUBMIT',
                $response['body'] ?? [],
                $response['http_status'],
                $this->correlationId,
                (int) $latencyMs
            );

            // FIX (E4): pemanggilan updateStatusFromResponse() sudah cocok dengan
            // signature di CustomsDocumentService (documentId, ceisaStatus, body,
            // nomorAju, nomorPendaftaran).
            $documentService->updateStatusFromResponse(
                $document->id,
                $response['ceisa_status'],
                $response['body'],
                $response['nomor_aju'] ?? null,
                $response['nomor_pendaftaran'] ?? null
            );

        } catch (\Exception $e) {
            // logError() signature sudah benar; correlationId dijamin ter-assign
            // dari blok E1 sebelum baris ini dieksekusi.
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

    private function callCeisaApi(CustomsDocument $document, array $payload): array
    {
        // TODO: Ganti dengan call ke CeisaH2HClient yang asli
        return [
            'http_status' => 200,
            'body' => ['status' => 'SUBMIT_SUCCESS'],
            'ceisa_status' => 'SUBMIT_SUCCESS',
            'nomor_aju' => 'AJU-' . uniqid(),
            'nomor_pendaftaran' => null,
        ];
    }

    private string $correlationId;

    private function generateCorrelationId(): string
    {
        return 'CEISA_' . uniqid() . '_' . time();
    }
}