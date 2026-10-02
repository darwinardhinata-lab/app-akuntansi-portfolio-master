<?php

namespace App\Support;

use App\Models\JournalHeader;
use App\Models\PaymentPlan;
use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class PaymentPlanProtection
{
    /** Call inside a transaction after locking the Payment Plan row. */
    public static function editable(PaymentPlan $payment): void
    {
        if (in_array($payment->status_payment, ['PAID', 'POSTED'], true)
            || self::hasJournal($payment)) {
            throw ValidationException::withMessages(['payment' => __('erp.payment_locked_guard')]);
        }
    }

    public static function hasJournal(PaymentPlan $payment): bool
    {
        return ! empty($payment->journal_id)
            || JournalHeader::where('journal_id', JournalHeader::idForPaymentPlan($payment->no_transaksi))->exists()
            || JournalHeader::where('source_doc_no', $payment->no_transaksi)
                ->where('transaction_type', 'Payment Plan')->exists()
            || JournalHeader::where('evidence_number', 'JRN-'.$payment->no_transaksi)->exists();
    }

    public static function unreceivedOrders(PaymentPlan $payment): void
    {
        $orders = PurchaseOrder::where(function ($query) use ($payment) {
            $query->where('po_number', 'PO-'.$payment->no_transaksi);
            if ($payment->ref_po_number) {
                $query->orWhere('po_number', $payment->ref_po_number);
            }
        })->lockForUpdate()->get();

        foreach ($orders as $order) {
            if (in_array($order->status, ['PARTIAL', 'RECEIVED', 'PARTIAL_RECEIVED', 'FULLY_RECEIVED'], true)
                || $order->receipt_mode === 'GRN_V1'
                || $order->details()->where('qty_received', '>', 0)->exists()) {
                throw ValidationException::withMessages(['payment' => __('erp.payment_received_guard')]);
            }
        }
    }
}
