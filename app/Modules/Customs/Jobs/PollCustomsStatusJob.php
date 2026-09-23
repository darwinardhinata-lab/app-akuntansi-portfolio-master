<?php

namespace App\Modules\Customs\Jobs;

use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\CustomsDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollCustomsStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CustomsDocumentService $documentService): void
    {
        $documents = CustomsDocument::submitted()
            ->whereNull('nomor_pendaftaran')
            ->get();

        foreach ($documents as $document) {
            $this->checkStatus($document, $documentService);
        }
    }

    private function checkStatus(CustomsDocument $document, CustomsDocumentService $documentService): void
    {
        // TODO: Ganti dengan call ke CeisaH2HClient::checkStatus()
        // Untuk saat ini, simulate respons

        Log::info("Polling status dokumen {$document->internal_number}", [
            'document_id' => $document->id,
        ]);
    }
}
