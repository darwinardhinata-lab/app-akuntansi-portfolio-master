<?php

namespace App\Modules\Platform\Support;

use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;

/** Read contract only. Never infer bill ownership from a session, vendor or journal number. */
final class PurchaseBillReadAccess
{
    public function authorize(Request $request, PurchaseBill $bill): void
    {
        abort_unless(config('platform.order_company_scope_enabled'), 409, 'Ownership A2 harus aktif untuk membaca tagihan.');
        $company = app(OperationalCompany::class)->authorizeRequest($request);
        $user = User::find($request->user()->id);
        abort_unless($user && app(CompanyContext::class)->allows($user, $company, 'purchase_bill.view'), 403);

        // Re-query persisted links: do not trust preloaded relations or caller-supplied attributes.
        $poId = PurchaseBill::whereKey($bill->getKey())->value('purchase_order_id');
        abort_unless($poId && PurchaseOrder::whereKey($poId)->where('company_id', $company->id)->exists(),
            404, 'Tagihan tidak memiliki bukti kepemilikan PO yang dapat diakses.');
    }
}