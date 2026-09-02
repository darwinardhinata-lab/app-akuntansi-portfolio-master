<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDashboardToTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 jam jika butuh waktu lama

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('SyncDashboardToTempJob started.');

        try {
            // N6 FIX: Proses per chunk journal_id (bukan loop per tanggal -> ratusan query).
            $now = now();
            $totalSynced = 0;

            DB::connection('dashboard')
                ->table('journals')
                ->whereDate('journal_date', '>=', '2026-01-01')
                ->whereDate('journal_date', '<=', date('Y-m-d'))
                ->select('journal_id')
                ->orderBy('journal_id')
                ->chunkById(1000, function ($rows) use ($now, &$totalSynced) {
                    $journalIds = $rows->pluck('journal_id')->toArray();
                    if (empty($journalIds)) {
                        return;
                    }

                    // 1. MAPPING HEADERS (Kolom Baru Dimasukkan ke Sini)
                    $headers = DB::connection('dashboard')
                        ->table('journals')
                        ->whereIn('journal_id', $journalIds)
                        ->get()
                        ->map(function ($item) use ($now) {
                        return [
                            'journal_id' => $item->journal_id,
                            'journal_date' => $item->journal_date,
                            'journal_code' => $item->journal_code,
                            'source_doc_no' => $item->source_doc_no,
                            'invoice_id' => $item->invoice_id,
                            'payment_id' => $item->payment_id,
                            'bill_id' => $item->bill_id,
                            'sales_ret_id' => $item->sales_ret_id,
                            'purch_ret_id' => $item->purch_ret_id,
                            'item_adj_id' => $item->item_adj_id,
                            'journal_type' => $item->journal_type,
                            'is_opening_balance' => $item->is_opening_balance,
                            'transaction_type' => $item->transaction_type,
                            'debit' => $item->debit,
                            'credit' => $item->credit,
                            'journal_description' => $item->journal_description,
                            'sync_status' => 'PENDING',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                        ->toArray();

                    if (!empty($headers)) {
                        foreach (array_chunk($headers, 500) as $chunk) {
                            DB::table('jh_temp')->insertOrIgnore($chunk);
                        }

                        $journalIds = array_column($headers, 'journal_id');

                        if (!empty($journalIds)) {
                            // 2. MAPPING DETAILS (Tetap sama karena detail nggak ada tambahan kolom)
                            $details = DB::connection('dashboard')
                                ->table('journal_details')
                                ->whereIn('journal_id', $journalIds)
                                ->get()
                                ->map(function ($item) use ($now) {
                                return [
                                    'journal_detail_id' => $item->journal_detail_id,
                                    'journal_id' => $item->journal_id,
                                    'coa_id' => $item->coa_id,
                                    'description' => $item->description,
                                    'debit' => $item->debit,
                                    'credit' => $item->credit,
                                    'tag_id' => $item->tag_id,
                                    'tag_name' => $item->tag_name,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            })
                                ->toArray();

                            if (!empty($details)) {
                                foreach (array_chunk($details, 500) as $chunk) {
                                    DB::table('jd_temp')->insertOrIgnore($chunk);
                                }
                            }
                        }
                    }

                    $totalSynced += count($journalIds);

                    // Jeda 0.1 detik biar server santai
                    usleep(100000);
                }, 'journal_id');

            Log::info('Synced ' . $totalSynced . ' journal headers from dashboard.');
            Log::info('SyncDashboardToTempJob completed successfully.');

            // Pemicu (Trigger) untuk lanjut ke tahap mapping (Job Tahap 2)
            \App\Jobs\ProcessPendingTempJob::dispatch();
            Log::info('ProcessPendingTempJob dispatched.');

        } catch (\Exception $e) {
            Log::error('SyncDashboardToTempJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}