<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncSODashboardToTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 jam jika butuh waktu lama

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('SyncSODashboardToTempJob started.');

        try {
            // Ambil semua tanggal transaksi unik dari tabel salesorder (koneksi dashboard)
            $dates = DB::connection('dashboard')
                ->table('salesorder')
                ->whereDate('transaction_date', '>=', '2026-01-01')
                ->whereDate('transaction_date', '<=', date('Y-m-d'))
                ->select(DB::raw('DATE(transaction_date) as date'))
                ->distinct()
                ->pluck('date');

            Log::info('Found ' . $dates->count() . ' distinct dates to sync for SO.');

            $now = now(); // Ambil waktu sekarang

            foreach ($dates as $date) {
                // 1. MAPPING HEADERS dengan chunk
                DB::connection('dashboard')
                    ->table('salesorder')
                    ->whereDate('transaction_date', $date)
                    ->orderBy('salesorder_id')
                    ->chunk(200, function ($headerChunk) use ($now) {
                        $headers = [];
                        $soIds = [];

                        foreach ($headerChunk as $item) {
                            $arr = (array)$item;
                            $arr['status_sync'] = 'PENDING';
                            $arr['updated_at'] = $now;
                            $headers[] = $arr;
                            $soIds[] = $item->salesorder_id;
                        }

                        if (!empty($headers)) {
                            // Insert headers bertahap
                            foreach (array_chunk($headers, 100) as $chunk) {
                                DB::table('so_temp')->insertOrIgnore($chunk);
                            }

                            // 2. MAPPING DETAILS (untuk $soIds di chunk ini saja)
                            if (!empty($soIds)) {
                                $details = DB::connection('dashboard')
                                    ->table('salesorder_details')
                                    ->whereIn('salesorder_id', $soIds)
                                    ->get()
                                    ->map(function ($item) {
                                        return (array)$item;
                                    })
                                    ->toArray();

                                if (!empty($details)) {
                                    foreach (array_chunk($details, 100) as $chunk) {
                                        DB::table('sod_temp')->insertOrIgnore($chunk);
                                    }
                                }
                            }
                        }
                    });

                // Jeda 0.1 detik biar server santai setelah memproses 1 hari
                usleep(100000);
            }

            Log::info('SyncSODashboardToTempJob completed successfully.');

            // Trigger mapping tahap 2
            \App\Jobs\ProcessPendingSOTempJob::dispatch();
            Log::info('ProcessPendingSOTempJob dispatched.');

        } catch (\Exception $e) {
            Log::error('SyncSODashboardToTempJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
