<?php

namespace App\Services;

use App\Models\PaymentPlan;
use App\Models\PurchaseBill;
use Illuminate\Support\Facades\DB;

class BillPaymentAllocationService
{
    /** Called inside the posting transaction; the bill lock serializes its payments. */
    public function allocate(PaymentPlan $payment, string $journalId): void
    {
        $bill = PurchaseBill::where('bill_number', $payment->ref_bill_number)->lockForUpdate()->first();
        if (!$bill || !$bill->journal_id
            || strcasecmp(trim($bill->vendor_name ?? $bill->contact_name ?? ''), trim($payment->vendor_toko ?? '')) !== 0) {
            throw new \RuntimeException(__('erp.audit_allocation_guard'));
        }
        // Unallocated historical payments must be reconciled, not guessed or counted twice.
        $legacy = PaymentPlan::where('ref_bill_number', $bill->bill_number)
            ->where('status_payment', 'POSTED')->where('id_payment', '!=', $payment->id_payment)
            ->whereNotIn('id_payment', DB::table('bill_payment_allocations')->select('id_payment'))->exists();
        $amount = round($payment->nominal_aktual_efektif, 2);
        $paid = (float) DB::table('bill_payment_allocations')->where('purchase_bill_id', $bill->id)->sum('amount');
        $remaining = round((float) $bill->grand_total - $paid, 2);
        if ($legacy || $bill->payment_status === 'PAID' || $amount <= 0 || $amount > $remaining) {
            throw new \RuntimeException(__('erp.audit_allocation_guard'));
        }
        DB::table('bill_payment_allocations')->insert([
            'purchase_bill_id' => $bill->id, 'id_payment' => $payment->id_payment,
            'journal_id' => $journalId, 'amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bill->update(['payment_status' => round($remaining - $amount, 2) === 0.0 ? 'PAID' : 'PARTIAL']);
    }
}