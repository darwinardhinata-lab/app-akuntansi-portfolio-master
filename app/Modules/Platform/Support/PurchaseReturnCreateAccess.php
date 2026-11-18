<?php

namespace App\Modules\Platform\Support;

use Illuminate\Http\Request;

final class PurchaseReturnCreateAccess
{
    public function companyId(Request $request): int
    {
        abort_unless(config('platform.order_company_scope_enabled'), 409, 'Ownership A2 harus aktif untuk mencatat retur.');
        $company = app(OperationalCompany::class)->authorizeRequest($request);
        abort_unless(app(CompanyContext::class)->allows($request->user(), $company, 'purchase_return.create'), 403);

        return (int) $company->id;
    }
}