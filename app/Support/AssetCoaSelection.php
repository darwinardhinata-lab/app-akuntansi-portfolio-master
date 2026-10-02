<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Asset;
use App\Models\JournalDetail;
use Illuminate\Validation\ValidationException;

class AssetCoaSelection
{
    public static function editable(Asset $asset): void
    {
        if (JournalDetail::where('description', 'Depresiasi aset #'.$asset->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Aset sudah dijurnal depresiasi; perubahan konfigurasi memerlukan koreksi berotorisasi.']);
        }
    }

    public static function validate(Asset $asset, string $category, int $life, ?string $expense): ?string
    {
        $categories = config('asset_coa.categories');
        if (! array_key_exists($category, $categories)) {
            self::reject();
        }
        self::account($category, 'DEBET', 'NERACA');
        if ($asset->journal_detail_id && (string) $asset->journalDetail?->account_code !== $category) {
            self::reject();
        }
        $accumulated = $categories[$category];
        if ($accumulated === null) {
            if ($life !== 0 || $expense) {
                self::reject();
            }

            return null;
        }
        if ($life < 1 || ! in_array($expense, config('asset_coa.expenses'), true)) {
            self::reject();
        }
        self::account($accumulated, 'KREDIT', 'NERACA');
        self::account($expense, 'DEBET', 'LABA RUGI');
        if (bccomp((string) $asset->residual_value, '0', 2) < 0
            || bccomp((string) $asset->purchase_price, (string) $asset->residual_value, 2) <= 0) {
            self::reject();
        }

        return $accumulated;
    }

    private static function account(string $code, string $normal, string $report): void
    {
        $account = Account::find($code);
        if (! $account || $account->normal_balance !== $normal || $account->report_pos !== $report) {
            self::reject();
        }
    }

    private static function reject(): never
    {
        throw ValidationException::withMessages(['category' => __('erp.asset_mapping_guard')]);
    }
}
