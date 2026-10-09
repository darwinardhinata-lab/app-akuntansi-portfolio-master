<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\JournalBalanceValidator;

class AuditPaymentAllocations extends Command
{
    protected $signature = 'audit:payment-allocations {--json : Emit one JSON object per finding and summary} {--documents : Also reconcile document balances and statuses against active allocations}';
    protected $description = 'Read-only verification of AR/AP allocation journal evidence (no historical matching or repair)';

    public function handle(): int
    {
        foreach (['invoice_payment_allocations', 'bill_payment_allocations'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error('Required allocation migrations missing: '.$table);
                return self::FAILURE;
            }
        }
        if (!Schema::hasColumn('invoice_payment_allocations', 'reversed_at')) {
            $this->error('Required AR reversal migration missing.');
            return self::FAILURE;
        }
        DB::disableQueryLog();
        $checked = $findings = 0;
        foreach (['AR', 'AP'] as $type) {
            $table = $type === 'AR' ? 'invoice_payment_allocations' : 'bill_payment_allocations';
            foreach (DB::table($table)->orderBy('id')->cursor() as $allocation) {
                $checked++;
                $document = DB::table($type === 'AR' ? 'sales_invoices' : 'purchase_bills')
                    ->where('id', $type === 'AR' ? $allocation->sales_invoice_id : $allocation->purchase_bill_id)->first();
                $issues = [];
                if (!$document) $issues[] = 'missing_document';
                $reference = $type === 'AR' ? ($document->invoice_number ?? null) : null;
                if ($type === 'AP') {
                    $payment = DB::table('transaksi_payment_plan')->where('id_payment', $allocation->id_payment)->first();
                    if (!$payment) {
                        $issues[] = 'missing_payment';
                    } else {
                        $reference = $payment->no_transaksi;
                        if ($payment->status_payment !== 'POSTED' || $payment->journal_id !== $allocation->journal_id
                            || $payment->ref_bill_number !== ($document->bill_number ?? null)) $issues[] = 'payment_reference_mismatch';
                        if (bccomp((string) ($payment->nominal_aktual ?? $payment->nominal), (string) $allocation->amount, 2) !== 0) $issues[] = 'payment_amount_mismatch';
                    }
                }
                $issues = array_merge($issues, $this->journalIssues($allocation->journal_id, $reference,
                    $type === 'AR' ? config('coa.piutang_usaha') : config('coa.hutang_usaha'),
                    $type === 'AR' ? 'KREDIT' : 'DEBET', $allocation->amount));
                if ($type === 'AR' && $allocation->reversed_at) {
                    if (!$allocation->reversed_by || !trim((string) $allocation->reversal_reason)) $issues[] = 'missing_reversal_audit';
                    foreach ($this->journalIssues($allocation->reversal_journal_id, $reference, config('coa.piutang_usaha'), 'DEBET', $allocation->amount) as $issue) {
                        $issues[] = 'reversal_'.$issue;
                    }
                }
                if ($issues) {
                    $findings++;
                    $result = ['type' => $type, 'allocation_id' => $allocation->id, 'issues' => $issues];
                    $this->line($this->option('json') ? json_encode($result, JSON_THROW_ON_ERROR) : $type.' #'.$allocation->id.': '.implode(', ', $issues));
                }
            }
        }
        if ($this->option('documents')) {
            $findings += $this->auditDocuments();
        }
        $this->line($this->option('json') ? json_encode(['checked' => $checked, 'findings' => $findings], JSON_THROW_ON_ERROR)
            : "Checked: {$checked}; findings: {$findings}. No data changed.");
        return $findings ? self::FAILURE : self::SUCCESS;
    }

    private function auditDocuments(): int
    {
        $findings = 0;
        foreach (['AR', 'AP'] as $type) {
            $isAr = $type === 'AR';
            foreach (DB::table($isAr ? 'sales_invoices' : 'purchase_bills')->orderBy('id')->cursor() as $document) {
                $issues = [];
                $allocations = DB::table($isAr ? 'invoice_payment_allocations' : 'bill_payment_allocations')
                    ->where($isAr ? 'sales_invoice_id' : 'purchase_bill_id', $document->id);
                if ($isAr) $allocations->whereNull('reversed_at');
                $paid = bcadd((string) $allocations->sum('amount'), '0', 2);
                $refund = $isAr ? bcadd((string) DB::table('sales_returns')->where('sales_invoice_id', $document->id)
                    ->whereIn('status', ['APPROVE', 'FAILED_DELIVERY'])->sum('total_refund_amount'), '0', 2) : '0.00';
                $total = bcadd((string) ($document->grand_total ?? 0), '0', 2);
                $remaining = bcsub(bcsub($total, $paid, 2), $refund, 2);
                $expected = bccomp($remaining, '0', 2) === 0 ? 'PAID'
                    : (bccomp(bcadd($paid, $refund, 2), '0', 2) > 0 ? 'PARTIAL' : 'UNPAID');
                if (bccomp($total, '0', 2) < 0 || bccomp($paid, '0', 2) < 0 || bccomp($refund, '0', 2) < 0) $issues[] = 'negative_document_or_credit';
                if (bccomp($remaining, '0', 2) < 0) {
                    $issues[] = 'negative_remaining_balance';
                    $expected = null; // Over-allocation is invalid, not a recommended payment status.
                }
                if ($isAr && $document->cancelled_at) {
                    // Cancellation has its own lifecycle; never classify it as an ordinary unpaid bill.
                    if (bccomp($paid, '0', 2) !== 0 || bccomp($refund, '0', 2) !== 0) $issues[] = 'cancelled_document_has_active_credit';
                    $expected = null;
                } elseif ($expected !== null && $document->payment_status !== $expected) {
                    $issues[] = 'document_status_mismatch';
                }
                if (!$document->journal_id) {
                    $issues[] = 'missing_source_journal';
                } else {
                    $sourceIssues = $this->journalIssues($document->journal_id,
                        $isAr ? $document->invoice_number : $document->bill_number,
                        $isAr ? config('coa.piutang_usaha') : config('coa.hutang_usaha'),
                        $isAr ? 'DEBET' : 'KREDIT', $total);
                    foreach ($sourceIssues as $issue) $issues[] = 'source_'.$issue;
                }
                if ($issues) {
                    $findings++;
                    $result = ['type' => $type, 'document_id' => $document->id, 'active_paid' => $paid,
                        'approved_refund' => $refund, 'remaining_balance' => $remaining,
                        'actual_status' => $document->payment_status, 'expected_status' => $expected, 'issues' => $issues];
                    $this->line($this->option('json') ? json_encode($result, JSON_THROW_ON_ERROR)
                        : $type.' document #'.$document->id.': '.implode(', ', $issues).' (remaining '.$remaining.')');
                }
            }
        }
        return $findings;
    }

    private function journalIssues(?string $id, ?string $reference, string $account, string $position, mixed $amount): array
    {
        $journal = $id ? \App\Support\ProtectedJournalQuery::table('journal_headers')->where('journal_id', $id)->first() : null;
        if (!$journal) return ['missing_journal'];
        $issues = [];
        if ($reference !== null && $journal->source_doc_no !== $reference) $issues[] = 'journal_reference_mismatch';
        $lines = \App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $id)->get();
        if ($lines->isEmpty() || !$lines->every(fn ($line) => in_array($line->position, ['DEBET', 'KREDIT'], true) && (float) $line->amount > 0)
            || !JournalBalanceValidator::isBalanced($lines->map(fn ($line) => (array) $line)->all())) $issues[] = 'invalid_journal_balance';
        $net = '0.00';
        foreach ($lines->where('account_code', $account) as $line) {
            $net = $line->position === $position ? bcadd($net, (string) $line->amount, 2) : bcsub($net, (string) $line->amount, 2);
        }
        if (bccomp($net, (string) $amount, 2) !== 0 || bccomp((string) $amount, '0', 2) <= 0) $issues[] = 'allocation_amount_mismatch';
        return $issues;
    }
}