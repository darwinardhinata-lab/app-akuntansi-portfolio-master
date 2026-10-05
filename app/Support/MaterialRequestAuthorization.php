<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use Illuminate\Auth\Access\AuthorizationException;

class MaterialRequestAuthorization
{
    public static function canView(?User $user): bool
    {
        return self::listed($user, 'view') || self::canCreate($user) || self::listed($user, 'approve');
    }

    public static function canCreate(?User $user): bool
    {
        return self::listed($user, 'create');
    }

    public static function canSubmit(?User $user, MaterialPurchaseRequest $request): bool
    {
        return self::canView($user) && (int) $request->created_by === (int) $user->id
            && $request->approval_status === MaterialPurchaseRequest::DRAFT;
    }

    public static function canApprove(?User $user, MaterialPurchaseRequest $request): bool
    {
        // FIX: izin eksplisit tidak membolehkan pembuat atau submitter menyetujui/menolak PR sendiri.
        return self::listed($user, 'approve')
            && $request->approval_status === MaterialPurchaseRequest::SUBMITTED
            && (int) $request->created_by !== (int) $user->id
            && (int) $request->submitted_by !== (int) $user->id;
    }

    public static function ensure(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('Anda tidak berwenang melakukan aksi Purchase Request ini.');
        }
    }

    private static function listed(?User $user, string $action): bool
    {
        // FIX: default kosong menolak akses; string ADMIN tidak memberikan bypass.
        if (! $user) {
            return false;
        }

        foreach (config('platform.pr_'.$action.'_user_ids', []) as $id) {
            if (ctype_digit((string) $id) && (int) $id > 0 && (int) $id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }
}
