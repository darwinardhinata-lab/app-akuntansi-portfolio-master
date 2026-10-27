<?php

namespace App\Support;

use App\Models\User;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Http\Request;

final class ErpPanelAccess
{
    public function allows(Request $request, ?User $user, string $permission = 'erp.panel.access'): bool
    {
        if (!config('platform.filament_enabled', false) || !$user || !$user->exists) return false;
        // Re-query identity and membership, not the hydrated Livewire user/record state.
        $fresh = User::find($user->id);
        if (!$fresh) return false;
        $context = app(CompanyContext::class);
        $company = $context->selected($request);
        return $company && $company->code === 'MGI'
            && \App\Modules\Platform\Models\Company::query()->count() === 1
            && $context->allows($fresh, $company, $permission);
    }
}