<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use Illuminate\Support\Facades\DB;

final class PurchaseReturnEligibility
{
    public function lockOrder(int $companyId, string $key, bool $byNumber = false): PurchaseOrder
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Return eligibility requires an active transaction.');
        }
        $orders = PurchaseOrder::where('company_id', $companyId)
            ->where($byNumber ? 'po_number' : 'id', $key)->limit(2)->lockForUpdate()->get();
        if ($orders->count() !== 1 || !in_array($orders->first()->status, ['PARTIAL', 'RECEIVED'], true)) {
            throw new \RuntimeException(__('erp.audit_return_guard'));
        }
        return $orders->first();
    }

    /** Caller must hold the PO lock obtained by lockOrder until commit. */
    public function assertLine(PurchaseOrder $po, PurchaseOrderDetail $line, int $quantity): void
    {
        $prior = DB::table('purchase_return_details as d')->join('purchase_returns as r', 'r.id', '=', 'd.purchase_return_id')
            ->where('r.purchase_order_id', $po->id)->where('d.item_code', $line->item_code)
            ->whereIn('r.status', ['COMPLETED', 'REVERSED'])->lockForUpdate()->get(['r.id', 'r.status', 'd.qty_returned']);
        // MariaDB does not propagate FOR UPDATE into a nested proof subquery.
        $reversed = DB::table('purchase_return_reversals')->whereIn('purchase_return_id', $prior->where('status', 'REVERSED')->pluck('id'))
            ->lockForUpdate()->pluck('purchase_return_id')->flip();
        $used = $prior->reject(fn ($row) => $row->status === 'REVERSED' && $reversed->has($row->id))->sum('qty_returned');
        if ((int) $line->purchase_order_id !== (int) $po->id || $quantity < 1
            || $po->details()->where('item_code', $line->item_code)->limit(2)->lockForUpdate()->get(['id'])->count() !== 1
            || bccomp(bcadd((string) $quantity, (string) $used, 6), (string) $line->qty_received, 6) > 0) {
            throw new \RuntimeException(__('erp.audit_return_guard'));
        }
    }
}