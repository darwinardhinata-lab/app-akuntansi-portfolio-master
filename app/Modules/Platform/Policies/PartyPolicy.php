<?php

namespace App\Modules\Platform\Policies;

use App\Models\User;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Support\CompanyContext;

class PartyPolicy
{
    public function __construct(private CompanyContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'party.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'party.create');
    }

    public function update(User $user, Party $party): bool
    {
        $company = $this->context->selected(request());

        return $company && (int) $party->company_id === (int) $company->id
            && $this->context->allows($user, $company, 'party.update');
    }

    private function allowed(User $user, string $permission): bool
    {
        $company = $this->context->selected(request());

        return $company && $this->context->allows($user, $company, $permission);
    }
}
