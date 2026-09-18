<?php

namespace App\Modules\Customs\Jobs;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\CeisaH2HClient;
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
            // Fase 2 (Task D): respons asli CeisaH2HClient hanya berisi
            // http_status/body/raw. Status dipetakan dari http_status
            // (2xx = SUBMIT_SUCCESS) — [BELUM PASTI - TODO] mapping status resmi
            // CEISA perlu dikonfirmasi ke dokumentasi DJBC (GAPS_TO_CONFIRM.md).
            $ceisaStatus = ($response['http_status'] >= 200 && $response['http_status'] < 300)
                ? 'SUBMIT_SUCCESS'
                : 'SUBMIT_FAILED';

            $documentService->updateStatusFromResponse(
                $document->id,
                $ceisaStatus,
                $response['body'] ?? [],
                $response['body']['nomor_aju'] ?? null,
                $response['body']['nomor_pendaftaran'] ?? null
            );

        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'tidak aktif')) {
                // Modul memang sengaja dimatikan — bukan kegagalan transient,
                // jangan retry otomatis. Log dulu sebelum fail() supaya audit
                // tetap tercatat (fail() melempar ulang exception).
                CustomsAuditLogger::logError(
                    $document->id,
                    $e->getMessage(),
                    $this->correlationId,
                    ['type' => 'module_disabled']
                );

                $this->fail($e);

                return;
            }

            // RuntimeException lain (mis. kredensial signing kosong) — gagal cepat,
            // retry tidak akan membantu karena bukan masalah transient.
            throw $e;
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
        // Fase 2 (Task D): simulasi palsu dihapus — sekarang benar-benar memanggil
        // CeisaH2HClient, yang akan melempar RuntimeException jika modul tidak
        // aktif (mencegah "pura-pura sukses" saat CEISA_ENABLED=false).
        return app(CeisaH2HClient::class)->submit(
            $document->document_type,
            $payload,
            $this->correlationId
        );
    }

    private string $correlationId;

    private function generateCorrelationId(): string
    {
        return 'CEISA_' . uniqid() . '_' . time();
    }
}