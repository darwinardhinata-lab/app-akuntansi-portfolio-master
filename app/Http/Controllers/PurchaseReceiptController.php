<?php

namespace App\Http\Controllers;

use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptController extends Controller
{
    private function companyId(): int
    {
        abort_unless(config('platform.order_company_scope_enabled'), 409, 'Ownership A2 harus aktif.');
        return app(OperationalCompany::class)->id();
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        $search = trim((string) $request->input('search', ''));
        $receipts = DB::table('purchase_receipts as r')
            ->leftJoin('purchase_orders as p', 'p.id', '=', 'r.purchase_order_id')
            ->leftJoin('purchase_bills as b', 'b.id', '=', 'r.purchase_bill_id')
            ->where('r.company_id', $companyId)->where('p.company_id', $companyId)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('r.receipt_number', 'like', '%'.$search.'%')
                ->orWhere('p.po_number', 'like', '%'.$search.'%')->orWhere('b.bill_number', 'like', '%'.$search.'%')))
            ->select('r.*', 'p.po_number', 'b.bill_number', 'b.vendor_name', 'b.grand_total')
            ->orderByDesc('r.id')->paginate(30)->withQueryString();
        return view('purchase_receipts.index', compact('receipts', 'search'));
    }

    public function show(int $id)
    {
        $companyId = $this->companyId();
        $receipt = DB::table('purchase_receipts')->where('id', $id)->where('company_id', $companyId)->first();
        abort_unless($receipt, 404);
        $po = \App\Models\PurchaseOrder::findOrFail($receipt->purchase_order_id);
        $bill = DB::table('purchase_bills')->where('id', $receipt->purchase_bill_id)->first();
        $details = DB::table('purchase_receipt_details')->where('purchase_receipt_id', $id)->orderBy('id')->get();
        return view('purchase_receipts.show', compact('receipt', 'po', 'bill', 'details'));
    }
}
