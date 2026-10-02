<?php

namespace App\Support;

use App\Models\User;

class PaymentPlanCorrectionAccess
{
    public static function allowed(?User $user): bool
    {
        if (! $user || $user->role !== 'FINANCE') {
            return false;
        }

        foreach (config('platform.payment_correction_user_ids', []) as $id) {
            if (ctype_digit((string) $id) && (int) $id > 0 && (int) $id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
