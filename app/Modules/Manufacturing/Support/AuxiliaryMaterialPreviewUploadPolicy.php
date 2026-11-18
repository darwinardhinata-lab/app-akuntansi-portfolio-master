<?php

namespace App\Modules\Manufacturing\Support;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use Illuminate\Http\Request;

final class AuxiliaryMaterialPreviewUploadPolicy
{
    /**
     * Scoped material-preview upload policy: authorizes uploading temporary bytes
     * strictly for read-only preview inspection. Does NOT grant reconciliation
     * attachment rights (ap.reconcile.propose/audit) or any financial write privileges.
     */
    public function allows(?Request $request, ?User $user): bool
    {
        if (! $request || ! $user) {
            return false;
        }

        if (! config('platform.filament_enabled', false)) {
            return false;
        }

        $access = app(ErpPanelAccess::class);

        // Must have ERP panel access and material.view permission
        if (! $access->allows($request, $user) || ! $access->allows($request, $user, 'material.view')) {
            return false;
        }

        // Must satisfy single-MGI company boundary
        try {
            $company = app(CompanyContext::class)->requireCompany($request);
            if ($company->code !== 'MGI' || Company::count() !== 1) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
