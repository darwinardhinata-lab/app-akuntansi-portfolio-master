<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncInvDashboardToTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 jam jika butuh waktu lama

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('SyncInvDashboardToTempJob started.');

        try {
            // Gunakan chunking langsung dari dashboard agar memori tidak habis
            DB::connection('dashboard')
                ->table('invoices')
                ->whereDate('transaction_date', '>=', '2026-01-01')
                ->orderBy('invoice_id')
                ->chunk(100, function ($headersChunk) {
                    
                    if ($headersChunk->isEmpty()) {
                        return;
                    }

                    // 1. SIAPKAN DATA HEADER UNTUK DI-INSERT
                    $headersToInsert = [];
                    foreach ($headersChunk as $item) {
                        $arr = (array)$item;
                        $arr['status_sync'] = 'PENDING';
                        $headersToInsert[] = $arr;
                    }

                    // Insert Header
                    DB::table('inv_temp')->insertOrIgnore($headersToInsert);

                    $invIds = array_column($headersToInsert, 'invoice_id');

                    if (!empty($invIds)) {
                        // 2. AMBIL DETAIL HANYA UNTUK CHUNK INI
                        $detailsChunk = DB::connection('dashboard')
                            ->table('invoice_details')
                            ->whereIn('invoice_id', $invIds)
                            ->get();

                        if ($detailsChunk->isNotEmpty()) {
                            $detailsToInsert = [];
                            foreach ($detailsChunk as $det) {
                                $detailsToInsert[] = (array)$det;
                            }
                            
                            // Insert Detail (bisa dipotong lagi jika 1 faktur punya ratusan item)
                            foreach (array_chunk($detailsToInsert, 100) as $detChunk) {
                                DB::table('invd_temp')->insertOrIgnore($detChunk);
                            }
                        }
                    }

                    // Beri jeda sangat singkat agar koneksi/server tidak tersedak
                    usleep(50000); // 0.05 detik
                });

            Log::info('SyncInvDashboardToTempJob completed successfully.');

            // Trigger mapping tahap 2
            \App\Jobs\ProcessPendingInvTempJob::dispatch();
            Log::info('ProcessPendingInvTempJob dispatched.');

        } catch (\Exception $e) {
            Log::error('SyncInvDashboardToTempJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
