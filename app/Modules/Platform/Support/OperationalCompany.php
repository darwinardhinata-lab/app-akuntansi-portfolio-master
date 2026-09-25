<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Company;
use Illuminate\Http\Request;

/** Transitional single-company guard: shared stock/journals are not multi-tenant yet. */
class OperationalCompany
{
    public function enabled(): bool
    {
        return (bool) config('platform.order_company_scope_enabled', false);
    }

    public function company(): Company
    {
        abort_if(config('platform.legacy_sync_enabled', true), 409, 'Nonaktifkan sinkronisasi legacy sebelum operasi A2.');
        abort_if(config('customs.enabled', false), 409, 'Integrasi customs belum mendukung ownership A2.');
        $companies = Company::query()->limit(2)->get();
        abort_unless($companies->count() === 1, 409, 'A2 hanya mendukung satu perusahaan operasional MGI.');
        $company = $companies->first();
        abort_unless($company->code === 'MGI' && $company->active, 409, 'Perusahaan operasional harus MGI aktif.');

        return $company;
    }

    public function authorizeRequest(Request $request): Company
    {
        $company = $this->company();
        abort_unless($request->user(), 403, 'Login diperlukan untuk dokumen operasional.');
        $selected = app(CompanyContext::class)->selected($request);
        abort_unless($selected && (int) $selected->id === (int) $company->id, 409,
            'Pilih perusahaan MGI yang dapat Anda akses sebelum membuka transaksi.');

        return $company;
    }

    public function id(): int
    {
        // Console import is explicitly restricted to the sole configured MGI company.
        // HTTP requests must additionally have a valid current-company membership.
        $request = request();
        if (! app()->runningInConsole() || $request->route() !== null) {
            return (int) $this->authorizeRequest($request)->id;
        }

        return (int) $this->company()->id;
    }
}
