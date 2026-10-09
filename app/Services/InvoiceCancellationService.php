<?php

namespace App\Services;

use App\Models\InventoryLedger;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Support\JournalBalanceValidator;
use Illuminate\Support\Facades\DB;

class InvoiceCancellationService
{
    public function cancel(int|string $id): SalesInvoice
    {
        return DB::transaction(function () use ($id) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($id);
            if ($invoice->sales_order_id) {
                $order = SalesOrder::lockForUpdate()->findOrFail($invoice->sales_order_id);
            }
            if ($invoice->cancelled_at) {
                return $invoice;
            }
            \App\Support\AccountingPeriodGuard::source([$invoice->transaction_date, now()->toDateString()]);
            if ($invoice->payment_status !== 'UNPAID'
                || (\Illuminate\Support\Facades\Schema::hasTable('invoice_payment_allocations')
                    && DB::table('invoice_payment_allocations')->where('sales_invoice_id', $invoice->id)->exists())
                || SalesReturn::where('sales_invoice_id', $invoice->id)->lockForUpdate()->exists()) {
                throw new \RuntimeException(__('erp.audit_invoice_cancel_blocked'));
            }

            $journals = JournalHeader::where(function ($query) use ($invoice) {
                if ($invoice->journal_id) {
                    $query->where('journal_id', $invoice->journal_id);
                } else {
                    $query->where('source_doc_no', $invoice->invoice_number)
                        ->orWhere('evidence_number', $invoice->invoice_number);
                }
            })->lockForUpdate()->get();
            if ($journals->count() !== 1) {
                throw new \RuntimeException(__('erp.audit_invoice_journal_invalid'));
            }
            $original = $journals->first();
            $details = $original->details()->lockForUpdate()->get();
            if ($details->isEmpty() || ! JournalBalanceValidator::isBalanced($details->toArray())) {
                throw new \RuntimeException(__('erp.audit_invoice_journal_invalid'));
            }

            $reversal = JournalHeader::create([
                'journal_id' => 'JRN-INV-CANCEL-'.$invoice->id,
                'transaction_date' => now()->toDateString(),
                'source_doc_no' => $invoice->invoice_number,
                'journal_type' => 'AUTO',
                'transaction_type' => 'SALES INVOICE REVERSAL',
                'notes' => 'Pembatalan faktur '.$invoice->invoice_number.'; jurnal sumber '.$original->getKey(),
            ]);
            foreach ($details as $detail) {
                if (! in_array($detail->position, ['DEBET', 'KREDIT'], true)) {
                    throw new \RuntimeException(__('erp.audit_invoice_journal_invalid'));
                }
                $reversal->details()->create([
                    'account_code' => $detail->account_code, 'helper_code' => $detail->helper_code,
                    'position' => $detail->position === 'DEBET' ? 'KREDIT' : 'DEBET',
                    'amount' => $detail->amount,
                ]);
            }

            $movements = InventoryLedger::where('evidence_number', $invoice->invoice_number)
                ->where('type', 'OUT')->orderBy('product_id')->lockForUpdate()->get();
            foreach ($movements->groupBy('product_id') as $productId => $rows) {
                $product = Product::lockForUpdate()->findOrFail($productId);
                $qty = (float) $rows->sum('qty');
                $value = (float) $rows->sum('total_cost');
                if ($qty <= 0 || $value < 0 || (float) $product->stock_quantity < 0) {
                    throw new \RuntimeException(__('erp.audit_invoice_journal_invalid'));
                }
                $newQty = (float) $product->stock_quantity + $qty;
                $newValue = round((float) $product->stock_quantity * (float) $product->average_cost + $value, 2);
                $average = round($newValue / $newQty, 2);
                $product->update(['stock_quantity' => $newQty, 'average_cost' => $average]);
                InventoryLedger::create([
                    'transaction_date' => now()->toDateString(), 'evidence_number' => $reversal->evidence_number,
                    'product_id' => $productId, 'type' => 'IN', 'qty' => $qty,
                    'unit_cost' => round($value / $qty, 2), 'total_cost' => $value,
                    'running_qty' => $newQty, 'running_value' => $newValue, 'moving_average_cost' => $average,
                    'description' => 'Pembatalan faktur '.$invoice->invoice_number,
                ]);
            }
            $invoice->forceFill([
                'cancelled_at' => now(), 'cancelled_by' => auth()->id(),
                'cancellation_journal_id' => $reversal->getKey(),
            ])->save();
            if (isset($order)) {
                $order->update(['status' => 'APPROVED']);
            }

            return $invoice;
        });
    }
}