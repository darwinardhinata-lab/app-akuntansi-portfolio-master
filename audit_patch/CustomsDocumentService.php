<?php

namespace App\Modules\Customs\Services;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Models\CustomsStatusHistory;
use App\Support\DocumentSequence;
use Illuminate\Support\Facades\DB;

class CustomsDocumentService
{
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
            ]);

            return $document;
        });
    }

    // FIX (T5): method submit()/retry()/void()/updateFromPayload()/updateStatusFromResponse()
    // di bawah ini SEBELUMNYA TIDAK ADA SAMA SEKALI di file ini, padahal sudah dipanggil dari
    // CustomsDocumentController dan SubmitCustomsDocumentJob. Akibatnya setiap klik tombol
    // Submit/Retry/Void/Update selalu berujung "Error: Call to undefined method
    // App\Modules\Customs\Services\CustomsDocumentService::submit()" (fatal PHP Error, bukan
    // exception biasa yang bisa ditangkap try/catch di controller). Modul Customs sebelumnya
    // hanya bisa membuat draft; seluruh siklus hidup dokumen setelahnya lumpuh total.

    /**
     * Antrekan dokumen untuk disubmit ke CEISA (dieksekusi async lewat SubmitCustomsDocumentJob).
     */
    public function submit(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        if (! $document->canBeSubmitted()) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} berstatus {$document->status}, tidak dapat disubmit."
            );
        }

        return DB::transaction(function () use ($document, $actorId) {
            $document->status = CustomsDocument::STATUS_QUEUED;
            $document->updated_by = $actorId;
            $document->save();

            $this->recordStatusHistory($document, CustomsDocument::STATUS_QUEUED, 'Dokumen diantrekan untuk submit ke CEISA', $actorId);

            \App\Modules\Customs\Jobs\SubmitCustomsDocumentJob::dispatch($document->id);

            return $document->refresh();
        });
    }

    /**
     * Retry submit untuk dokumen yang ditolak/perlu koreksi.
     */
    public function retry(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        $retryableStatuses = [CustomsDocument::STATUS_REJECTED, CustomsDocument::STATUS_NEED_CORRECTION];

        if (! in_array($document->status, $retryableStatuses, true)) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} berstatus {$document->status}, tidak dapat di-retry. " .
                'Retry hanya untuk status REJECTED atau NEED_CORRECTION.'
            );
        }

        return DB::transaction(function () use ($document, $actorId) {
            $document->retry_count += 1;
            $document->last_error_code = null;
            $document->last_error_message = null;
            $document->status = CustomsDocument::STATUS_QUEUED;
            $document->updated_by = $actorId;
            $document->save();

            $this->recordStatusHistory(
                $document,
                CustomsDocument::STATUS_QUEUED,
                "Retry submit dipicu (percobaan ke-{$document->retry_count})",
                $actorId
            );

            \App\Modules\Customs\Jobs\SubmitCustomsDocumentJob::dispatch($document->id);

            return $document->refresh();
        });
    }

    /**
     * Batalkan dokumen. Hanya boleh dari status DRAFT/QUEUED (belum benar-benar terkirim ke CEISA).
     */
    public function void(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        $voidableStatuses = [CustomsDocument::STATUS_DRAFT, CustomsDocument::STATUS_QUEUED];

        if (! in_array($document->status, $voidableStatuses, true)) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} berstatus {$document->status}, tidak dapat dibatalkan. " .
                'Hanya dokumen berstatus DRAFT atau QUEUED yang boleh di-void.'
            );
        }

        return DB::transaction(function () use ($document, $actorId) {
            $document->status = CustomsDocument::STATUS_VOIDED;
            $document->updated_by = $actorId;
            $document->save();

            $this->recordStatusHistory($document, CustomsDocument::STATUS_VOIDED, 'Dokumen dibatalkan oleh user', $actorId);

            return $document->refresh();
        });
    }

    /**
     * Update field dokumen (kode_kantor, currency, exchange_rate) sebelum disubmit.
     */
    public function updateFromPayload(CustomsDocument $document, array $payload): CustomsDocument
    {
        if (! $document->isEditable()) {
            throw new \RuntimeException(
                "Dokumen {$document->internal_number} berstatus {$document->status}, tidak dapat diedit."
            );
        }

        $document->fill($payload);
        $document->save();

        return $document->refresh();
    }

    /**
     * Terapkan hasil respons CEISA (submit atau polling) ke dokumen.
     * Dipanggil dari SubmitCustomsDocumentJob & PollCustomsStatusJob.
     */
    public function updateStatusFromResponse(
        int $documentId,
        string $ceisaStatus,
        array $responseBody,
        ?string $nomorAju = null,
        ?string $nomorPendaftaran = null
    ): CustomsDocument {
        return DB::transaction(function () use ($documentId, $ceisaStatus, $nomorAju, $nomorPendaftaran) {
            /** @var CustomsDocument $document */
            $document = CustomsDocument::lockForUpdate()->findOrFail($documentId);

            $mappedStatus = $this->mapCeisaStatus($ceisaStatus);
            $document->status = $mappedStatus;
            $document->submitted_at = $document->submitted_at ?? now();
            $document->responded_at = now();

            if ($nomorAju) {
                $document->nomor_aju = $nomorAju;
            }
            if ($nomorPendaftaran) {
                $document->nomor_pendaftaran = $nomorPendaftaran;
            }

            $document->save();

            $this->recordStatusHistory($document, $mappedStatus, "Update status dari CEISA: {$ceisaStatus}", null);

            return $document;
        });
    }

    /**
     * Pemetaan status mentah dari CEISA ke status internal (konstanta CustomsDocument::STATUS_*).
     * Status yang tidak dikenali diperlakukan sebagai UNDER_REVIEW (aman, tidak dianggap final)
     * daripada diabaikan diam-diam -- supaya operator tahu perlu cek manual.
     */
    private function mapCeisaStatus(string $ceisaStatus): string
    {
        return match (strtoupper($ceisaStatus)) {
            'SUBMIT_SUCCESS', 'SUBMITTED' => CustomsDocument::STATUS_SUBMITTED,
            'UNDER_REVIEW' => CustomsDocument::STATUS_UNDER_REVIEW,
            'NEED_CORRECTION' => CustomsDocument::STATUS_NEED_CORRECTION,
            'SPPB_ISSUED' => CustomsDocument::STATUS_SPPB_ISSUED,
            'NPE_ISSUED' => CustomsDocument::STATUS_NPE_ISSUED,
            'REJECTED' => CustomsDocument::STATUS_REJECTED,
            default => CustomsDocument::STATUS_UNDER_REVIEW,
        };
    }

    private function recordStatusHistory(CustomsDocument $document, string $status, string $note, ?int $actorId): void
    {
        CustomsStatusHistory::create([
            'customs_document_id' => $document->id,
            'status' => $status,
            'note' => $note,
            'changed_at' => now(),
        ]);
    }
}

