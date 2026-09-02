<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class ProcessExistingAssets extends Command
{
    protected $signature = 'assets:process-existing';
    protected $description = 'Process existing journal entries with account code 12000 to create asset records';

    public function handle()
    {
        $this->info('Memproses journal entries dengan akun 12000...');

        $details = DB::table('journal_details')
            ->where('account_code', '12000')
            ->where('position', 'DEBET')
            ->select(
                'journal_details.id as detail_id',
                'journal_details.journal_id',
                'journal_details.amount',
                'journal_details.created_at'
            )
            ->get();

        $this->info("Ditemukan {$details->count()} journal detail dengan akun 12000 (debet)");

        $headers = JournalHeader::pluck('transaction_date', 'journal_id')
            ->toArray();

        $headerDescriptions = JournalHeader::pluck('description', 'journal_id')
            ->toArray();

        $assetsToCreate = [];
        $now = now();
        $created = 0;
        $skipped = 0;

        foreach ($details as $detail) {
            $exists = Asset::where('journal_detail_id', $detail->detail_id)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            $purchaseDate = $headers[$detail->journal_id] ?? $now->format('Y-m-d');
            $dateStr = date('Ymd', strtotime($purchaseDate));

            $assetsToCreate[] = [
                'journal_detail_id'  => $detail->detail_id,
                'asset_code'         => 'AST-' . $detail->detail_id . '-' . $dateStr,
                'asset_name'         => $headerDescriptions[$detail->journal_id] ?? 'Aset Tetap',
                'purchase_date'      => $purchaseDate,
                'purchase_price'     => $detail->amount,
                'useful_life_months' => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        if (!empty($assetsToCreate)) {
            foreach (array_chunk($assetsToCreate, 200) as $chunk) {
                Asset::insert($chunk);
                $created += count($chunk);
            }
        }

        $this->info("✅ Selesai! {$created} aset baru dibuat, {$skipped} dilewati (sudah ada).");
        
        return 0;
    }
}