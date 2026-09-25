<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\OperationalCompany;
use Closure;
use Illuminate\Http\Request;

class RequireOperationalCompany
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(OperationalCompany::class);
        if (! $context->enabled() || ! $request->user()
            || $request->routeIs('platform.*', 'login', 'logout', 'lang.switch')) {
            return $next($request);
        }
        // Also protects legacy modules that still use raw SQL/shared stock/journals.
        $company = $context->authorizeRequest($request);
        if ($request->routeIs('po.store', 'po.update', 'so.store', 'so.update')) {
            abort_unless((string) $request->input('context_company_id') === (string) $company->id,
                409, 'Formulir tidak memiliki konteks MGI yang sesuai. Muat ulang formulir.');
            abort_if(array_key_exists('company_id', $request->all()), 422, 'company_id ditentukan server, bukan formulir.');
        }

        return $next($request);
    }
}
