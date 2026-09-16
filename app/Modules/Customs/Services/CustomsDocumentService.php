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
}
