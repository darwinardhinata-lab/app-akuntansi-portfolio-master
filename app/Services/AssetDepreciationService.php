<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\JournalHeader;
use App\Support\AssetCoaSelection;
use App\Support\PostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetDepreciationService
{
    public function post(Carbon $date): JournalHeader
    {
        return DB::transaction(function () use ($date) {
            $period = $date->format('Ym');
            $source = 'DEP-'.$period;
            $id = 'JRN-DEP-'.$period;
            $assets = Asset::where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            if (JournalHeader::where('journal_id', $id)->orWhere('source_doc_no', $source)->orWhere('evidence_number', $source)->exists()) {
                throw new \RuntimeException('Depresiasi periode ini sudah diposting.');
            }
            $lines = [];
            foreach ($assets as $asset) {
                if ($asset->purchase_date->startOfMonth()->greaterThan($date->copy()->startOfMonth())) {
                    continue;
                }
                // Fail closed rather than presenting an incomplete batch as complete.
                if ($asset->useful_life_months === 0 && ! $asset->category) {
                    throw new \RuntimeException('Aset aktif #'.$asset->id.' belum diklasifikasi; lengkapi mapping sebelum batch.');
                }
                $accumulated = AssetCoaSelection::validate($asset, (string) $asset->category, $asset->useful_life_months, $asset->depreciation_expense_code);
                if ($accumulated === null) {
                    continue;
                }
                $start = $asset->purchase_date;
                $month = ((int) $date->format('Y') - (int) $start->format('Y')) * 12 + (int) $date->format('m') - (int) $start->format('m');
                if ($month < 0 || $month >= $asset->useful_life_months) {
                    continue;
                }
                $base = bcsub((string) $asset->purchase_price, (string) $asset->residual_value, 2);
                $monthly = bcdiv($base, (string) $asset->useful_life_months, 2);
                $amount = $month === $asset->useful_life_months - 1
                    ? bcsub($base, bcmul($monthly, (string) ($asset->useful_life_months - 1), 2), 2) : $monthly;
                if (bccomp($amount, '0', 2) <= 0) {
                    continue;
                }
                foreach ([$asset->depreciation_expense_code => 'DEBET', $accumulated => 'KREDIT'] as $code => $position) {
                    $lines[] = ['account_code' => (string) $code, 'position' => $position, 'amount' => $amount,
                        'description' => 'Depresiasi aset #'.$asset->id, 'created_at' => now(), 'updated_at' => now()];
                }
            }
            if (! $lines) {
                throw new \RuntimeException('Tidak ada aset terkonfigurasi yang perlu disusutkan.');
            }

            return PostingService::post(['journal_id' => $id, 'source_doc_no' => $source,
                'transaction_date' => $date->copy()->endOfMonth()->toDateString(),
                'transaction_type' => 'Penyusutan Aset', 'description' => 'Depresiasi '.$period], $lines);
        });
    }
}
