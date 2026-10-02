<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

class MaklunReversalAuthorization
{
    public static function allowed(?User $user): bool
    {
        return $user && $user->role === 'FINANCE'
            && in_array((string) $user->id, array_map('strval', config('platform.maklun_reversal_user_ids', [])), true);
    }

    public static function validate(string $reason): string
    {
        abort_unless(self::allowed(auth()->user()), 403);
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:1000'])->validate();

        return $reason;
    }
}
