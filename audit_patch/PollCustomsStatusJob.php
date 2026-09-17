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
        // FIX: tambah whereNotNull('nomor_aju') -- checkStatus() butuh nomor_aju untuk query
        // ke CEISA. Sebelumnya query ini bisa mengambil dokumen yang statusnya SUBMITTED/
        // UNDER_REVIEW tapi nomor_aju-nya belum terisi (mis. submit gagal parsial), yang akan
        // membuat checkStatus() dipanggil dengan nomor_aju null.
        $documents = CustomsDocument::submitted()
            ->whereNull('nomor_pendaftaran')
            ->whereNotNull('nomor_aju')
            ->get();

        foreach ($documents as $document) {
            $this->checkStatus($document, $documentService);
        }
    }

    /**
     * FIX (T9): SEBELUMNYA method ini hanya menulis Log::info() tanpa pernah memanggil
     * CEISA sama sekali -- "polling status" tidak pernah benar-benar mengecek apa pun.
     * Konsekuensinya dokumen yang sudah SUBMITTED bisa tertahan selamanya di status itu
     * karena tidak ada mekanisme lain yang memperbarui status/nomor_pendaftaran-nya.
     * Sekarang memanggil CeisaH2HClient::checkStatus() yang sesungguhnya, lalu meneruskan
     * hasilnya ke CustomsDocumentService::updateStatusFromResponse() (method baru, lihat
     * Fix #3) supaya status & nomor_pendaftaran ter-update otomatis.
     *
     * RuntimeException dari client (mis. CEISA_ENABLED masih false) ditangkap di sini secara
     * eksplisit dan hanya di-log sebagai warning -- TIDAK melempar ulang -- karena job ini
     * berjalan terjadwal untuk BANYAK dokumen sekaligus (lihat routes/console.php Fix #7);
     * satu dokumen gagal polling tidak boleh menghentikan pemrosesan dokumen lain dalam
     * batch yang sama.
     */
    private function checkStatus(CustomsDocument $document, CustomsDocumentService $documentService): void
    {
        try {
            /** @var \App\Modules\Customs\Services\CeisaH2HClient $client */
            $client = app(\App\Modules\Customs\Services\CeisaH2HClient::class);

            $response = $client->checkStatus($document->nomor_aju);
            $body = $response['body'] ?? [];
            $ceisaStatus = $body['status'] ?? null;

            if (! $ceisaStatus) {
                Log::warning("Respons status CEISA untuk {$document->internal_number} tidak memiliki field 'status'.", [
                    'document_id' => $document->id,
                    'body' => $body,
                ]);
                return;
            }

            $documentService->updateStatusFromResponse(
                $document->id,
                $ceisaStatus,
                $body,
                $body['nomor_aju'] ?? $document->nomor_aju,
                $body['nomor_pendaftaran'] ?? null
            );

            Log::info("Polling status dokumen {$document->internal_number} selesai: {$ceisaStatus}", [
                'document_id' => $document->id,
            ]);
        } catch (\RuntimeException $e) {
            Log::warning("Polling status dokumen {$document->internal_number} gagal: {$e->getMessage()}", [
                'document_id' => $document->id,
            ]);
        }
    }
}

