<?php

namespace App\Modules\PaymentPlan\Support;

use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Http\Request;

final class ErpActor
{
    public function resolve(Request $request): array
    {
        abort_unless($request->user(), 401);
        abort_unless(config('payment_plan_v33.enabled', false), 404);
        $company = app(OperationalCompany::class)->authorizeRequest($request);
        $user = $request->user();
        $mapping = config('payment_plan_v33.role_codes', []);
        $roles = $user->platformRoles()->wherePivot('company_id', $company->id)
            ->whereIn('code', array_keys($mapping))->pluck('code')->map(fn ($code) => $mapping[$code])->unique()->values()->all();
        abort_unless($roles, 403, 'Payment Plan role assignment required.');
        return [
            'id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email,
            'department' => (string) $user->id_divisi, 'roles' => $roles, 'company_id' => (int) $company->id,
        ];
    }
}