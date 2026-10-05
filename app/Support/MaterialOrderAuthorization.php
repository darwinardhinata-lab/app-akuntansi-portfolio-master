<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use Illuminate\Auth\Access\AuthorizationException;

class MaterialOrderAuthorization
{
    public static function canView(?User $user): bool
    {
        return self::listed($user, 'view') || self::canCreate($user) || self::listed($user, 'approve');
    }

    public static function canCreate(?User $user): bool
    {
        return self::listed($user, 'create');
    }

    public static function canSubmit(?User $user, MaterialPurchaseOrder $order): bool
    {
        return self::canCreate($user) && (int) $order->created_by === (int) $user->id && $order->approval_status === 'DRAFT';
    }

    public static function canApprove(?User $user, MaterialPurchaseOrder $order): bool
    {
        // FIX: SoD hanya terhadap pembuat/submitter PO, bukan pembuat PR sumber.
        return self::listed($user, 'approve') && $order->approval_status === 'SUBMITTED'
            && (int) $order->created_by !== (int) $user->id && (int) $order->submitted_by !== (int) $user->id;
    }

    public static function canEdit(?User $user, MaterialPurchaseOrder $order): bool
    {
        return self::canSubmit($user, $order);
    }

    public static function canRevise(?User $user, MaterialPurchaseOrder $order): bool
    {
        return self::canCreate($user) && (int) $order->created_by === (int) $user->id && $order->approval_status === 'REJECTED';
    }

    public static function ensure(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('Anda tidak berwenang melakukan aksi Material PO ini.');
        }
    }

    private static function listed(?User $user, string $action): bool
    {
        // FIX: allowlist kosong menolak akses, termasuk ADMIN tanpa izin eksplisit.
        if (! $user) {
            return false;
        }
        foreach (config('platform.po_'.$action.'_user_ids', []) as $id) {
            if (ctype_digit((string) $id) && (int) $id > 0 && (int) $id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
