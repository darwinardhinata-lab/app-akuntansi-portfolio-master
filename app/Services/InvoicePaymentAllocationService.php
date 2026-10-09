<?php

namespace App\Services;

use App\Models\JournalHeader;
use App\Models\SalesInvoice;
use App\Support\JournalBalanceValidator;
use App\Support\PaymentFundingAccount;
use Illuminate\Support\Facades\DB;

class InvoicePaymentAllocationService
{
    public function allocate(int $invoiceId, string $journalId, int $userId): void
    {
        DB::transaction(function () use ($invoiceId, $journalId, $userId) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($invoiceId);
            $journal = JournalHeader::lockForUpdate()->findOrFail($journalId);
            $existing = DB::table('invoice_payment_allocations')->where('journal_id', $journalId)->first();
            if ($existing && !$existing->reversed_at && (int) $existing->sales_invoice_id === $invoiceId) {
                return;
            }
            $lines = $journal->details()->lockForUpdate()->get();
            $cashCodes = PaymentFundingAccount::options()->pluck('account_code')->all();
            $amount = round((float) $lines->where('account_code', config('coa.piutang_usaha'))->where('position', 'KREDIT')->sum('amount'), 2);
            $cash = round((float) $lines->whereIn('account_code', $cashCodes)->where('position', 'DEBET')->sum('amount'), 2);
            $paid = (float) DB::table('invoice_payment_allocations')->where('sales_invoice_id', $invoiceId)->whereNull('reversed_at')->sum('amount');
            $refund = (float) DB::table('sales_returns')->where('sales_invoice_id', $invoiceId)
                ->whereNotIn('status', ['PENDING_INSPECTION', 'REJECT'])->sum('total_refund_amount');
            $source = JournalHeader::find($invoice->journal_id);
            $sourceAr = $source ? round((float) $source->details()->where('account_code', config('coa.piutang_usaha'))
                ->selectRaw("SUM(CASE WHEN position = 'DEBET' THEN amount ELSE -amount END) as balance")->value('balance'), 2) : 0;
            $validLines = $lines->every(fn ($line) => (float) $line->amount > 0 &&
                (($line->position === 'KREDIT' && $line->account_code === config('coa.piutang_usaha'))
                    || ($line->position === 'DEBET' && in_array($line->account_code, $cashCodes, true))));
            if ($existing || $invoice->cancelled_at || !in_array($invoice->payment_status, ['UNPAID', 'PARTIAL'], true)
                || !$source || $journalId === $invoice->journal_id || $journal->journal_type !== 'MANUAL'
                || $journal->source_doc_no !== $invoice->invoice_number
                || $journal->transaction_date < $invoice->transaction_date
                || !$validLines || !JournalBalanceValidator::isBalanced($lines->toArray())
                || $amount <= 0 || $amount !== $cash || $sourceAr !== round((float) $invoice->grand_total, 2)
                || $amount > round($sourceAr - $paid - $refund, 2)) {
                throw new \RuntimeException(__('erp.audit_invoice_allocation_guard'));
            }
            DB::table('invoice_payment_allocations')->insert([
                'sales_invoice_id' => $invoiceId, 'journal_id' => $journalId, 'amount' => $amount,
                'allocated_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $invoice->update(['payment_status' => round($sourceAr - $paid - $refund - $amount, 2) === 0.0 ? 'PAID' : 'PARTIAL']);
            \App\Models\SystemLog::record('UPDATE', 'Piutang', 'Allocation '.$journalId.' -> '.$invoice->invoice_number.'; amount '.$amount);
        });
    }

    public function reverse(int $allocationId, int $userId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 1000) {
            throw new \RuntimeException(__('erp.audit_invoice_allocation_guard'));
        }
        DB::transaction(function () use ($allocationId, $userId, $reason) {
            if (\App\Support\AccountingPeriodGuard::enabled()) \App\Support\AccountingPeriodGuard::lock();
            $reference = DB::table('invoice_payment_allocations')->where('id', $allocationId)->first();
            if (!$reference) {
                throw new \RuntimeException(__('erp.audit_invoice_allocation_guard'));
            }
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($reference->sales_invoice_id);
            $allocation = DB::table('invoice_payment_allocations')->where('id', $allocationId)->lockForUpdate()->first();
            if ($allocation->reversed_at) {
                return;
            }
            $journal = JournalHeader::lockForUpdate()->findOrFail($allocation->journal_id);
            $lines = $journal->details()->lockForUpdate()->get();
            $cashCodes = PaymentFundingAccount::options()->pluck('account_code')->all();
            $amount = round((float) $lines->where('account_code', config('coa.piutang_usaha'))->where('position', 'KREDIT')->sum('amount'), 2);
            $cash = round((float) $lines->whereIn('account_code', $cashCodes)->where('position', 'DEBET')->sum('amount'), 2);
            $validLines = $lines->every(fn ($line) => (float) $line->amount > 0 &&
                (($line->position === 'KREDIT' && $line->account_code === config('coa.piutang_usaha'))
                    || ($line->position === 'DEBET' && in_array($line->account_code, $cashCodes, true))));
            $paid = round((float) DB::table('invoice_payment_allocations')->where('sales_invoice_id', $invoice->id)->whereNull('reversed_at')->sum('amount'), 2);
            $refund = round((float) DB::table('sales_returns')->where('sales_invoice_id', $invoice->id)
                ->whereNotIn('status', ['PENDING_INSPECTION', 'REJECT'])->sum('total_refund_amount'), 2);
            if ($invoice->cancelled_at || $journal->source_doc_no !== $invoice->invoice_number || $journal->journal_type !== 'MANUAL'
                || substr((string) $journal->transaction_date, 0, 10) > now()->toDateString()
                || !$validLines || !JournalBalanceValidator::isBalanced($lines->toArray()) || $amount <= 0
                || $amount !== $cash || $amount !== round((float) $allocation->amount, 2)
                || $paid + $refund > round((float) $invoice->grand_total, 2)) {
                throw new \RuntimeException(__('erp.audit_invoice_allocation_guard'));
            }
            $reversal = JournalHeader::create([
                'transaction_date' => now()->toDateString(), 'source_doc_no' => $invoice->invoice_number,
                'transaction_type' => 'AR Receipt Reversal', 'journal_type' => 'REVERSAL',
                'notes' => 'Reverse '.$journal->getKey().': '.trim($reason),
            ]);
            $reversedLines = $lines->map(fn ($line) => [
                'account_code' => $line->account_code, 'helper_code' => $line->helper_code,
                'position' => $line->position === 'DEBET' ? 'KREDIT' : 'DEBET', 'amount' => $line->amount,
            ])->all();
            if (!JournalBalanceValidator::isBalanced($reversedLines)) {
                throw new \RuntimeException(__('erp.audit_invoice_allocation_guard'));
            }
            $reversal->details()->createMany($reversedLines);
            DB::table('invoice_payment_allocations')->where('id', $allocationId)->update([
                'reversal_journal_id' => $reversal->getKey(), 'reversed_by' => $userId,
                'reversed_at' => now(), 'reversal_reason' => trim($reason), 'updated_at' => now(),
            ]);
            $remainingPaid = round($paid - $amount, 2);
            $invoice->update(['payment_status' => round((float) $invoice->grand_total - $remainingPaid - $refund, 2) === 0.0
                ? 'PAID' : ($remainingPaid > 0 ? 'PARTIAL' : 'UNPAID')]);
            \App\Models\SystemLog::record('UPDATE', 'Piutang', 'Reverse allocation '.$allocationId.'; journal '.$reversal->getKey().'; '.trim($reason));
        });
    }
}