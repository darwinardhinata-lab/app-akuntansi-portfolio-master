<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = app(CompanyContext::class)->selected($request);
        if (! $company) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('platform.company.edit')
                    ->with('error', 'Pilih perusahaan aktif terlebih dahulu.');
            }
            abort(409, 'Perusahaan aktif tidak tersedia. Pilih ulang perusahaan.');
        }

        // A stale browser tab must not submit its data into the newly selected company.
        if (! $request->isMethodSafe()) {
            abort_unless((string) $request->input('context_company_id') === (string) $company->id,
                409, 'Perusahaan pada formulir berbeda. Muat ulang formulir sebelum menyimpan.');
        }

        return $next($request);
    }
}
