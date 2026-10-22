<?php

namespace App\Support;

use Illuminate\Http\Request;

class AccountFilter
{
    public static function codes(Request $request): array
    {
        $request->validate([
            'account_codes' => 'sometimes|array|max:100',
            'account_codes.*' => 'required|string|distinct|exists:accounts,account_code',
            'account_code' => 'sometimes|nullable|string|exists:accounts,account_code',
        ]);

        return array_values(array_unique($request->input('account_codes',
            $request->filled('account_code') ? [$request->input('account_code')] : [])));
    }
}