<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class SalesRevenueAccount
{
    public const CODES = ['LOCAL' => '411001', 'EXPORT' => '411002', 'SERVICE' => '411003'];

    public static function resolve(?string $semantic, bool $goodsFlow = true): string
    {
        $code = self::CODES[$semantic ?? ''] ?? null;
        if (! $code || ($goodsFlow && $semantic === 'SERVICE')) {
            throw ValidationException::withMessages(['sales_semantic' => __('erp.sales_semantic_guard')]);
        }
        $account = Account::find($code);
        if (! $account || $account->normal_balance !== 'KREDIT' || $account->report_pos !== 'LABA RUGI'
            || $account->coa_type !== 'Sales') {
            throw ValidationException::withMessages(['sales_semantic' => __('erp.sales_semantic_guard')]);
        }

        return $code;
    }
}
