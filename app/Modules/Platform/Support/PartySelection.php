<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Limits party selection only; this does not establish ownership of legacy PO/SO. */
class PartySelection
{
    public function available(Request $request, array $roles): Builder
    {
        $company = app(CompanyContext::class)->selected($request);

        return Party::query()
            ->where('company_id', $company?->id ?? 0)
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('role', $roles)->where('active', true));
    }

    public function resolve(Request $request, ?int $partyId, array $roles): ?Party
    {
        if ($partyId === null) {
            return null;
        }

        $party = $this->available($request, $roles)->whereKey($partyId)->first();
        if (! $party) {
            throw ValidationException::withMessages([
                'party_id' => 'Party tidak tersedia untuk perusahaan aktif atau peran dokumen ini.',
            ]);
        }

        return $party;
    }
}
