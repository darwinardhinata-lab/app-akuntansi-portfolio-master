<?php

namespace App\Modules\Manufacturing\Support;

use App\Models\User;

class MaterialReceiptVoidAuthorization
{
    public static function allows(?User $user): bool
    {
        return $user && $user->role === 'FINANCE'
            && in_array((string) $user->id, array_map('strval', config('platform.mrn_void_user_ids', [])), true);
    }
}