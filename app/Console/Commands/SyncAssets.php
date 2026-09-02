<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JournalDetail;
use App\Models\Asset;

class SyncAssets extends Command
{
    protected $signature = 'assets:sync';
    protected $description = 'Sinkronisasi aset dari jurnal';

    public function handle()
    {
        $items = JournalDetail::with('header')
            ->has('header')
            ->where('position', 'DEBET')
            ->where('account_code', 'LIKE', '12%')
            ->get();

        // FIX N+1: Kumpulkan data, gunakan upsert (1 query) bukan updateOrCreate per item
        $upsertData = [];
        $now = now();

        foreach ($items as $item) {
            if (empty($item->header->transaction_date)) continue;
            $tglBeli = $item->header->transaction_date;
            $upsertData[] = [
                'journal_detail_id'  => $item->getKey(),
                'asset_code'         => 'AST-' . $item->getKey() . '-' . date('Ymd', strtotime($tglBeli)),
                'asset_name'         => $item->header->description ?: 'Aset Tetap Tanpa Nama',
                'purchase_date'      => $tglBeli,
                'purchase_price'     => $item->amount,
                'useful_life_months' => 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        foreach (array_chunk($upsertData, 500) as $chunk) {
            Asset::upsert(
                $chunk,
                ['journal_detail_id'],
                // useful_life_months TIDAK diupdate — biarkan nilai yang sudah diset user
                ['asset_code', 'asset_name', 'purchase_date', 'purchase_price', 'updated_at']
            );
        }

        $this->info('Sync selesai: ' . count($upsertData) . ' aset.');
    }
}