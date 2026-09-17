<?php
// Helper script untuk rebuild CustomsDocumentService.php
// Eksekusi via: php _rebuild_customs_service.php

$file = __DIR__ . '/app/Modules/Customs/Services/CustomsDocumentService.php';

$content = <<<'ENDOFPHP'
<?php

namespace App\Modules\Customs\Services;

use App\Modules\Customs\Jobs\SubmitCustomsDocumentJob;
use App\Modules\Customs\Jobs\PollCustomsStatusJob;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Models\CustomsStatusHistory;
use App\Modules\Customs\Services\CeisaH2HClient;
use App\Support\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomsDocumentService
{
    public function __construct(
        private readonly CeisaH2HClient $ceisaClient
    ) {}

    public function createDraft(
        string $sourceType,
        int $sourceId,
        string $documentType,
        ?int $createdBy = null
    ): CustomsDocument {
        return DB::transaction(function () use ($sourceType, $sourceId, $documentType, $createdBy) {
            $existing = CustomsDocument::where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('document_type', $documentType)
                ->first();

            if ($existing) {
                throw new \RuntimeException(
                    "Dokumen Customs {$documentType} sudah ada"
                );
            }

            $prefix = $documentType . '/' . now()->format('Y/m') . '/';
            $internalNumber = DocumentSequence::generateSecure(
                'cst_customs_documents',
                'internal_number',
                $prefix,
                5
            );

            $document = CustomsDocument::create([
                'document_type' => $documentType,
                'internal_number' => $internalNumber,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'DRAFT',
                'environment' => config('customs.default_environment', 'sandbox'),
                'created_by' => $createdBy,
            ]);

            CustomsStatusHistory::create([
                'customs_document_id' => $document->id,
                'status' => 'DRAFT',
                'note' => 'Dokumen draft dibuat',
                'changed_at' => now(),

    /**
     * FIX (T5-missing-methods): Submit dokumen ke CEISA H2H.
     * Hanya boleh dipanggil jika status dokumen adalah DRAFT.
     * Mengubah status jadi QUEUED dan mendispatch SubmitCustomsDocumentJob.
     */
    public function submit(CustomsDocument $document, ?int $submittedBy = null): CustomsDocument
    {
        // FIX (T6-status-guard): Pastikan hanya dokumen DRAFT yang bisa diajukan.
        if (! $document->canBeSubmitted()) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} tidak dapat diajukan. " .
                "Status saat ini: {$document->status}. Hanya dokumen DRAFT yang dapat disubmit."
            );
        }

        return DB::transaction(function () use ($document, $submittedBy) {
            $document->status = 'QUEUED';
            $document->submitted_at = now();
            $document->save();

            CustomsStatusHistory::create([
                'customs_document_id' => $document->id,
                'status' => 'QUEUED',
                'note' => 'Dokumen diajukan ke CEISA H2H',
                'changed_at' => now(),
            ]);

            // FIX (T6-job-wire): Dispatch job asinkronus untuk submit ke CEISA.
            SubmitCustomsDocumentJob::dispatch($document->id);

            Log::info('Customs document submitted for processing', [
                'internal_number' => $document->internal_number,
                'document_id' => $document->id,
                'submitted_by' => $submittedBy,
            ]);

            return $document;
        });
    }

            ]);

            // FIX (T5-missing-methods): Dispatch job polling untuk mengecek status dokumen yang baru dibuat.
            PollCustomsStatusJob::dispatch($document->id);

            return $document;

    /**
     * FIX (T5-missing-methods): Retry submit dokumen yang gagal.
     * Hanya boleh dipanggil jika status dokumen adalah QUEUED atau SUBMITTED.
     * Membatasi maksimum 3x retry.
     */
    public function retry(CustomsDocument $document, ?int $retriedBy = null): CustomsDocument
    {
        if (! in_array($document->status, ['QUEUED', 'SUBMITTED'], true)) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} tidak dapat di-retry. " .
                "Status saat ini: {$document->status}. Hanya dokumen QUEUED atau SUBMITTED yang dapat di-retry."
            );
        }

        if ($document->retry_count >= 3) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} sudah mencapai maksimum retry (3x). " .
                "Tidak dapat melakukan retry lagi."
            );
        }

        return DB::transaction(function () use ($document, $retriedBy) {
            $document->status = 'QUEUED';
            $document->submitted_at = now();
            $document->retry_count = $document->retry_count + 1;
            $document->last_error_code = null;
            $document->last_error_message = null;
            $document->save();

            CustomsStatusHistory::create([
                'customs_document_id' => $document->id,
                'status' => 'QUEUED',
                'note' => "Retry submit ke-{$document->retry_count}",
                'changed_at' => now(),
                'changed_by' => $retriedBy,
            ]);

            SubmitCustomsDocumentJob::dispatch($document->id);

            Log::info('Customs document retry submitted', [
                'internal_number' => $document->internal_number,
                'document_id' => $document->id,
                'retry_count' => $document->retry_count,
                'retried_by' => $retriedBy,
            ]);

            return $document;
        });
    }

        });
    }

    /**
     * FIX (T5-missing-methods): Batalkan dokumen customs (void).
     * Hanya boleh dipanggil jika status dokumen adalah DRAFT atau QUEUED.
     */
    public function void(CustomsDocument $document, ?int $voidedBy = null): CustomsDocument
    {
        if (! in_array($document->status, ['DRAFT', 'QUEUED'], true)) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} tidak dapat dibatalkan (void). " .
                "Status saat ini: {$document->status}. Hanya dokumen DRAFT atau QUEUED yang dapat dibatalkan."
            );
        }

        return DB::transaction(function () use ($document, $voidedBy) {
            $document->status = 'VOIDED';
            $document->save();

            CustomsStatusHistory::create([
                'customs_document_id' => $document->id,
                'status' => 'VOIDED',
                'note' => 'Dokumen dibatalkan (void)',
                'changed_at' => now(),
                'changed_by' => $voidedBy,
            ]);

            Log::info('Customs document voided', [
                'internal_number' => $document->internal_number,
                'document_id' => $document->id,
                'voided_by' => $voidedBy,
            ]);

            return $document;
        });
    }

}
ENDOFPHP;

file_put_contents($file, $content);
echo "File $file berhasil ditulis.\n";


    /**
     * FIX (T5-missing-methods): Update metadata dokumen dari payload yang diterima dari CEISA.
     * Method ini dipanggil oleh controller saat user mengupdate dokumen manual.
     */
    public function updateFromPayload(CustomsDocument $document, array $payload): CustomsDocument
    {
        $updateData = [];

        // FIX (T10-api-secret): Pastikan api_secret tersedia sebelum proses signing.
        if (isset($payload['kode_kantor']) && is_string($payload['kode_kantor'])) {
            $updateData['kode_kantor'] = $payload['kode_kantor'];
        }

        if (isset($payload['currency']) && is_string($payload['currency'])) {
            $updateData['currency'] = $payload['currency'];
        }

        if (isset($payload['exchange_rate']) && is_numeric($payload['exchange_rate'])) {
            $updateData['exchange_rate'] = (float) $payload['exchange_rate'];
        }

        if (! empty($updateData)) {
            $document->update($updateData);
        }

        return $document->fresh();
    }


    /**
     * FIX (T5-missing-methods): Update status dokumen berdasarkan response dari CEISA.
     * Dipanggil setelah submit berhasil diproses oleh sistem CEISA.
     */
    public function updateStatusFromResponse(
        int $documentId,
        string $ceisaStatus,
        array $responseBody,
        ?string $nomorAju = null,
        ?string $nomorPendaftaran = null
    ): CustomsDocument {
        $document = CustomsDocument::findOrFail($documentId);

        // FIX: Mapping status dari CEISA ke status internal aplikasi.
        $statusMap = [
            'SUBMIT_SUCCESS'   => 'SUBMITTED',
            'SUBMIT_PENDING'   => 'SUBMITTED',
            'REJECTED'         => 'REJECTED',
            'UNDER_REVIEW'     => 'UNDER_REVIEW',
            'SPPB_ISSUED'      => 'SPPB_ISSUED',
            'NPE_ISSUED'       => 'NPE_ISSUED',
        ];

        $mappedStatus = $statusMap[$ceisaStatus] ?? $ceisaStatus;

        if (! in_array($mappedStatus, [
            'SUBMITTED', 'UNDER_REVIEW', 'SPPB_ISSUED', 'NPE_ISSUED', 'REJECTED',
        ], true)) {
            throw new \RuntimeException(
                "Status CEISA tidak dikenali: {$ceisaStatus}. " .
                "Tidak dapat mengupdate status dokumen."
            );
        }

        return DB::transaction(function () use ($document, $mappedStatus, $responseBody, $nomorAju, $nomorPendaftaran) {
            $updateData = [
                'status' => $mappedStatus,
            ];

            if ($nomorAju !== null) {
                $updateData['nomor_aju'] = $nomorAju;
            }

            if ($nomorPendaftaran !== null) {
                $updateData['nomor_pendaftaran'] = $nomorPendaftaran;
            }

            $document->update($updateData);

            CustomsStatusHistory::create([
                'customs_document_id' => $document->id,
                'status' => $mappedStatus,
                'note' => "Status diperbarui dari CEISA: {$ceisaStatus}",
                'changed_at' => now(),
                'response_payload' => json_encode($responseBody),
            ]);

            Log::info('Customs document status updated from CEISA response', [
                'internal_number' => $document->internal_number,
                'document_id' => $document->id,
                'ceisa_status' => $ceisaStatus,
                'mapped_status' => $mappedStatus,
            ]);

            return $document->fresh();
        });
    }
