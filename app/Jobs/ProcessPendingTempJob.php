<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPendingTempJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;

    public function __construct()
    {
        //
    }

    public function handle()
    {
        Log::info('ProcessPendingTempJob V3 started. Looking for PENDING data...');

        try {
            // ── FIX: ACCOUNT_ID MAPPING ────────────────────────────────────────────────────
            // `accounts` table menggunakan `account_code` (string) sebagai PRIMARY KEY dan `account_id` (int).
            // `jd_temp.coa_id` adalah INTEGER ID milik Jubelio yang berelasi ke `accounts.account_id`.
            // Kita tarik `account_code` dengan key `account_id` dari tabel `accounts` agar lookup:
            // $detail->coa_id (int) → $accountsCache[$detail->coa_id] → account_code (string) berfungsi.
            // ────────────────────────────────────────────────────────────────────────────
            $accountsCache = DB::table('accounts')
                ->whereNotNull('account_code')
                ->pluck('account_code', 'account_id')
                ->toArray();

            // Cache utama: account_id (integer) ke account_code (string)
            // Hapus blok fallback $accountsByIdCache karena tabel accounts tidak punya kolom 'id'

            // WAJIB pakai chunkById agar tidak ada data yang ter-skip saat update status
            DB::table('jh_temp')
                ->where('sync_status', 'PENDING')
                ->orderBy('journal_id')
                ->chunkById(200, function ($headers) use ($accountsCache) {

                    Log::info("Memproses " . count($headers) . " data header...");

                    // N5 FIX: Pre-load SEMUA jd_temp untuk seluruh chunk dalam SATU query (whereIn).
                    // Sebelumnya: 2 query per header (1 untuk tag, 1 untuk detail) = hingga 400 query/chunk.
                    // Setelah fix: 1 query untuk semua header dalam chunk.
                    $journalIds = collect($headers)->pluck('journal_id')->toArray();
                    $allDetailsGrouped = DB::table('jd_temp')
                        ->whereIn('journal_id', $journalIds)
                        ->get()
                        ->groupBy('journal_id');

                    foreach ($headers as $header) {
                        try {
                            DB::transaction(function () use ($header, $accountsCache, $allDetailsGrouped) {
                                // 1. Format ID Baru
                                $datePart = date('Ymd', strtotime($header->journal_date));
                                $newJournalId = "JRN-{$datePart}-{$header->journal_id}";

                                // 2. AMBIL DETAIL DARI MAP IN-MEMORY (N5 FIX: tidak query lagi)
                                $details = $allDetailsGrouped->get($header->journal_id, collect());

                                // Cari tag_name pertama yang ada isinya (nggak kosong)
                                $headerTag = null;
                                foreach ($details as $d) {
                                    if (!empty($d->tag_name)) {
                                        $headerTag = $d->tag_name;
                                        break; // Kalau udah ketemu 1, langsung berhenti nyari
                                    }
                                }

                                // 3. Suntik Header
                                DB::table('journal_headers')->updateOrInsert(
                                    ['jj_id' => $header->journal_id],
                                    [
                                        'journal_id' => $newJournalId,
                                        'transaction_date' => date('Y-m-d', strtotime($header->journal_date)),
                                        'evidence_number' => $header->journal_code,
                                        'description' => $header->source_doc_no,
                                        'transaction_type' => $header->transaction_type,
                                        'invoice_id' => $header->invoice_id,
                                        'payment_id' => $header->payment_id,
                                        'bill_id' => $header->bill_id,
                                        'sales_ret_id' => $header->sales_ret_id,
                                        'purch_ret_id' => $header->purch_ret_id,
                                        'item_adj_id' => $header->item_adj_id,
                                        'journal_type' => $header->journal_type,
                                        'is_opening_balance' => $header->is_opening_balance,
                                        'tags' => $headerTag,
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                    ]
                                );
                                // 4. Bersihkan Detail Lama
                                DB::table('journal_details')->where('journal_id', $newJournalId)->delete();

                                // 5. Jahit Detail (N5 FIX: gunakan $details dari map, bukan query ulang)
                                $detailInserts = [];
                                foreach ($details as $detail) {
                                    $coaId = $detail->coa_id;
                                    $accountCode = $accountsCache[$coaId] ?? null;

                                    if ($accountCode === null) {
                                        Log::warning("coa_id {$coaId} tidak ditemukan di master accounts (jh_temp: {$header->journal_id})");
                                        continue;
                                    }

                                    $position = $detail->debit > 0 ? 'DEBET' : 'KREDIT';
                                    $amount = $detail->debit > 0 ? $detail->debit : $detail->credit;

                                    $detailInserts[] = [
                                        'journal_id' => $newJournalId,
                                        'journal_no' => null,
                                        'account_id' => $coaId,
                                        'account_code' => $accountCode,
                                        'helper_code' => null,
                                        'position' => $position,
                                        'amount' => $amount,
                                        'created_at' => now()->toDateTimeString(),
                                        'updated_at' => now()->toDateTimeString(),
                                    ];
                                }

                                // 6. Suntik Massal Detail
                                if (!empty($detailInserts)) {
                                    DB::table('journal_details')->insert($detailInserts);
                                }

                                // 7. Tandai Berhasil
                                DB::table('jh_temp')
                                    ->where('journal_id', $header->journal_id)
                                    ->update([
                                        'sync_status' => 'SUCCESS',
                                        'updated_at' => now()->toDateTimeString()
                                    ]);
                            });
                        } catch (\Throwable $e) {
                            Log::error("Gagal menjahit ID {$header->journal_id}: " . $e->getMessage() . " (Baris " . $e->getLine() . ")");

                            DB::table('jh_temp')
                                ->where('journal_id', $header->journal_id)
                                ->update([
                                    'sync_status' => 'FAILED',
                                    'updated_at' => now()->toDateTimeString()
                                ]);
                        }
                    }
                }, 'journal_id'); // Patokan chunkById

            Log::info('ProcessPendingTempJob V3 completed successfully.');

        } catch (\Throwable $e) {
            Log::error('ProcessPendingTempJob V3 FATAL ERROR: ' . $e->getMessage());
            throw $e;
        }
    }
}