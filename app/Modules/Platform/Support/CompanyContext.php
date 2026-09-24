<?php

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Http\Request;

/** Request-based lookup: never cache a tenant across requests or queue jobs. */
class CompanyContext
{
    public const SESSION_KEY = 'platform.company_id';

    public function selected(Request $request): ?Company
    {
        $user = $request->user();
        if (! $user || ! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get(self::SESSION_KEY);
        if ($id !== null) {
            // A revoked selection must not silently fall back to another company.
            return $user->companies()->where('companies.active', true)->whereKey($id)->first();
        }

        $defaults = $user->companies()->where('companies.active', true)
            ->wherePivot('is_default', true)->get();

        return $defaults->count() === 1 ? $defaults->first() : null;
    }

    public function requireCompany(Request $request): Company
    {
        $company = $this->selected($request);
        abort_unless($company, 409, 'Pilih perusahaan aktif yang dapat Anda akses.');

        return $company;
    }

    public function allows(User $user, Company $company, string $permission): bool
    {
        // Recheck membership and active flag, including when called outside HTTP.
        if (! $user->companies()->whereKey($company->getKey())
            ->where('companies.active', true)->exists()) {
            return false;
        }

        return $user->platformRoles()->wherePivot('company_id', $company->getKey())
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();
    }
}
