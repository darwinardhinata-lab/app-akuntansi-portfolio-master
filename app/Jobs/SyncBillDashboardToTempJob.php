<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBillDashboardToTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 jam jika butuh waktu lama

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('SyncBillDashboardToTempJob started.');

        try {
            // Ambil semua tanggal transaksi unik dari tabel bills (koneksi dashboard)
            $dates = DB::connection('dashboard')
                ->table('bills')
                ->whereDate('transaction_date', '>=', '2026-01-01')
                ->whereDate('transaction_date', '<=', date('Y-m-d'))
                ->select(DB::raw('DATE(transaction_date) as date'))
                ->distinct()
                ->pluck('date');

            Log::info('Found ' . $dates->count() . ' distinct dates to sync for Bills.');

            $now = now(); // Ambil waktu sekarang

            foreach ($dates as $date) {
                // 1. MAPPING HEADERS
                $headers = DB::connection('dashboard')
                    ->table('bills')
                    ->whereDate('transaction_date', $date)
                    ->get()
                    ->map(function ($item) use ($now) {
                        $arr = (array)$item;
                        // Tambahkan status_sync jika diperlukan
                        $arr['status_sync'] = 'PENDING';
                        $arr['updated_at'] = $now;
                        return $arr;
                    })
                    ->toArray();

                if (!empty($headers)) {
                    foreach (array_chunk($headers, 100) as $chunk) {
                        DB::table('bill_temp')->insertOrIgnore($chunk);
                    }

                    $billIds = array_column($headers, 'bill_id');

                    if (!empty($billIds)) {
                        // 2. MAPPING DETAILS
                        $details = DB::connection('dashboard')
                            ->table('bill_details')
                            ->whereIn('bill_id', $billIds)
                            ->get()
                            ->map(function ($item) {
                                return (array)$item;
                            })
                            ->toArray();

                        if (!empty($details)) {
                            foreach (array_chunk($details, 100) as $chunk) {
                                DB::table('billd_temp')->insertOrIgnore($chunk);
                            }
                        }
                    }
                }

                // Jeda 0.1 detik biar server santai
                usleep(100000);
            }

            Log::info('SyncBillDashboardToTempJob completed successfully.');

            // Trigger mapping tahap 2
            \App\Jobs\ProcessPendingBillTempJob::dispatch();
            Log::info('ProcessPendingBillTempJob dispatched.');

        } catch (\Exception $e) {
            Log::error('SyncBillDashboardToTempJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
