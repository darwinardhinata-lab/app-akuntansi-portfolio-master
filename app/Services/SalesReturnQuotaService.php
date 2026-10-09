<?php

namespace App\Services;

use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class SalesReturnQuotaService
{
    /** Invoice must be locked by the caller, both when reserving and inspecting. */
    public function line(SalesInvoice $invoice, string $sku, int $qty, ?int $excludeReturn = null)
    {
        $lines = $invoice->details()->where('item_code', $sku)->get();
        // Ambiguous legacy duplicate lines require explicit reconciliation rather than guessing price.
        if ($lines->count() !== 1 || !$lines->first()->product_id) {
            throw new \RuntimeException(__('erp.audit_return_guard'));
        }
        $used = DB::table('sales_return_details as d')->join('sales_returns as r', 'r.id', '=', 'd.sales_return_id')
            ->where('r.sales_invoice_id', $invoice->id)->where('d.item_code', $sku)
            ->where('r.status', '!=', 'REJECT')
            ->when($excludeReturn, fn ($q) => $q->where('r.id', '!=', $excludeReturn))
            ->sum(DB::raw("CASE WHEN r.status = 'PENDING_INSPECTION' THEN d.qty_returned ELSE d.qty_approved END"));
        if ($qty < 0 || $qty + $used > (int) $lines->first()->qty_actual) {
            throw new \RuntimeException(__('erp.audit_return_guard'));
        }
        return $lines->first();
    }
}