<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class PaymentFundingAccount
{
    /** Verified IDR cash/current accounts in the supplied MGI COA export. */
    private const IDR_CODES = ['111001', '111002', '111101', '111102', '111103'];

    public static function options()
    {
        return Account::whereIn('account_code', self::IDR_CODES)
            ->where('coa_type', 'Cash & Bank')->where('normal_balance', 'DEBET')
            ->where('report_pos', 'NERACA')->orderBy('account_code')->get();
    }

    public static function resolve(?string $code): string
    {
        $account = self::options()->firstWhere('account_code', $code);
        if (! $account) {
            throw ValidationException::withMessages(['jenis_transaksi' => __('erp.payment_funding_guard')]);
        }

        return $account->account_code;
    }
}
