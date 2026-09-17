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
            ]);

            // FIX (T5-missing-methods): Dispatch job polling untuk mengecek status dokumen yang baru dibuat.
            PollCustomsStatusJob::dispatch($document->id);

            return $document;
        });
    }

    /**
     * FIX (C3 / Fase 1-B): method submit/retry/void/updateFromPayload/
     * updateStatusFromResponse sebelumnya dipanggil controller & job tapi tidak
     * pernah didefinisikan di sini -> fatal error saat modul dipakai.
     */

    /**
     * Ajukan dokumen untuk dikirim ke CEISA — hanya mengubah status ke QUEUED
     * dan dispatch job. TIDAK memanggil CEISA sungguhan di sini (itu tugas job).
     */
    public function submit(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        return DB::transaction(function () use ($document, $actorId) {
            $locked = CustomsDocument::lockForUpdate()->findOrFail($document->id);

            if (! $locked->canBeSubmitted()) {
                throw new \RuntimeException(
                    "Dokumen berstatus {$locked->status} tidak dapat diajukan."
                );
            }

            $locked->update([
                'status' => 'QUEUED',
                'updated_by' => $actorId,
            ]);

            CustomsStatusHistory::create([
                'customs_document_id' => $locked->id,
                'status' => 'QUEUED',
                'note' => 'Diajukan oleh user untuk dikirim ke CEISA',
                'changed_at' => now(),
            ]);

            SubmitCustomsDocumentJob::dispatch($locked->id);

            return $locked;
        });
    }

    /**
     * Retry submit untuk dokumen yang ditolak/butuh perbaikan.
     */
    public function retry(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        return DB::transaction(function () use ($document, $actorId) {
            $locked = CustomsDocument::lockForUpdate()->findOrFail($document->id);

            if (! in_array($locked->status, ['NEED_CORRECTION', 'REJECTED'], true)) {
                throw new \RuntimeException(
                    "Dokumen berstatus {$locked->status} tidak dapat di-retry."
                );
            }

            $locked->update([
                'status' => 'QUEUED',
                'retry_count' => $locked->retry_count + 1,
                'last_error_code' => null,
                'last_error_message' => null,
                'updated_by' => $actorId,
            ]);

            CustomsStatusHistory::create([
                'customs_document_id' => $locked->id,
                'status' => 'QUEUED',
                'note' => "Retry ke-{$locked->retry_count} oleh user",
                'changed_at' => now(),
            ]);

            SubmitCustomsDocumentJob::dispatch($locked->id);

            return $locked;
        });
    }

    /**
     * Batalkan dokumen — hanya boleh untuk dokumen yang belum final di CEISA.
     */
    public function void(CustomsDocument $document, ?int $actorId = null): CustomsDocument
    {
        return DB::transaction(function () use ($document, $actorId) {
            $locked = CustomsDocument::lockForUpdate()->findOrFail($document->id);

            if (! in_array($locked->status, ['DRAFT', 'NEED_CORRECTION', 'REJECTED'], true)) {
                throw new \RuntimeException(
                    "Dokumen berstatus {$locked->status} tidak dapat dibatalkan. " .
                    'Dokumen yang sudah SUBMITTED/SPPB_ISSUED/NPE_ISSUED tidak bisa di-void dari sini.'
                );
            }

            $locked->update([
                'status' => 'VOIDED',
                'updated_by' => $actorId,
            ]);

            CustomsStatusHistory::create([
                'customs_document_id' => $locked->id,
                'status' => 'VOIDED',
                'note' => 'Dibatalkan oleh user',
                'changed_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Update field header dokumen — hanya boleh saat masih DRAFT.
     */
    public function updateFromPayload(CustomsDocument $document, array $validated): CustomsDocument
    {
        return DB::transaction(function () use ($document, $validated) {
            $locked = CustomsDocument::lockForUpdate()->findOrFail($document->id);

            if ($locked->status !== 'DRAFT') {
                throw new \RuntimeException(
                    "Dokumen berstatus {$locked->status} tidak dapat diedit. Hanya dokumen DRAFT yang bisa diubah."
                );
            }

            $locked->update($validated);

            return $locked;
        });
    }

    /**
     * Dipanggil oleh SubmitCustomsDocumentJob / PollCustomsStatusJob setelah
     * menerima respons dari CEISA (atau simulasi TODO saat ini).
     *
     * NOTE: mapping status CEISA -> status internal masih sementara (pass-through
     * langsung dari $ceisaStatus) sampai spesifikasi resmi DJBC diterima —
     * lihat TODO di CeisaSignatureService/CeisaH2HClient.
     */
    public function updateStatusFromResponse(
        int $documentId,
        string $ceisaStatus,
        array $responseBody,
        ?string $nomorAju = null,
        ?string $nomorPendaftaran = null
    ): CustomsDocument {
        return DB::transaction(function () use ($documentId, $ceisaStatus, $nomorAju, $nomorPendaftaran) {
            $locked = CustomsDocument::lockForUpdate()->findOrFail($documentId);

            // Idempotency guard: kalau status sudah sama persis dan nomor sudah terisi, tidak usah diproses ulang.
            if ($locked->status === $ceisaStatus && $locked->nomor_aju === $nomorAju) {
                return $locked;
            }

            $locked->update([
                'status' => $ceisaStatus,
                'nomor_aju' => $nomorAju ?? $locked->nomor_aju,
                'nomor_pendaftaran' => $nomorPendaftaran ?? $locked->nomor_pendaftaran,
                'submitted_at' => $locked->submitted_at ?? now(),
                'responded_at' => now(),
            ]);

            CustomsStatusHistory::create([
                'customs_document_id' => $locked->id,
                'status' => $ceisaStatus,
                'note' => 'Update status dari respons CEISA',
                'changed_at' => now(),
            ]);

            return $locked;
        });
    }
}