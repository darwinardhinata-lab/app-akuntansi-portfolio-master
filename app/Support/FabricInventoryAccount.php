<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Validation\ValidationException;

class FabricInventoryAccount
{
    public const CODES = ['114003', '114008', '114002'];

    public static function resolve(?string $code): string
    {
        $account = in_array($code, self::CODES, true) ? Account::find($code) : null;
        if (! $account || $account->normal_balance !== 'DEBET' || $account->report_pos !== 'NERACA') {
            throw ValidationException::withMessages(['inventory_account_code' => __('erp.fabric_coa_guard')]);
        }

        return $code;
    }
}
