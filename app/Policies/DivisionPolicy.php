<?php

namespace App\Policies;

use App\Models\MasterDivisi;
use App\Models\User;
use App\Support\ErpPanelAccess;

final class DivisionPolicy
{
    private function allowed(User $user, string $action): bool
    {
        return app(ErpPanelAccess::class)->allows(request(), $user)
            && app(ErpPanelAccess::class)->allows(request(), $user, 'division.'.$action);
    }
    public function viewAny(User $user): bool { return $this->allowed($user, 'view'); }
    public function view(User $user, MasterDivisi $record): bool { return $this->allowed($user, 'view'); }
    public function create(User $user): bool { return $this->allowed($user, 'create'); }
    public function update(User $user, MasterDivisi $record): bool { return $this->allowed($user, 'update'); }
    public function delete(User $user, MasterDivisi $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}