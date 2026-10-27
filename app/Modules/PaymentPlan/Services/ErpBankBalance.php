<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Support\Facades\DB;

/** Read projection only: ERP GL plus actual debit events not yet represented in GL. */
final class ErpBankBalance
{
    public function project(int $company, array $account, array $settings): array
    {
        $cutoff = $settings['erp_cutoff'] ?? null;
        if (!is_string($cutoff) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $cutoff)
            || !checkdate((int) substr($cutoff, 5, 2), (int) substr($cutoff, 8, 2), (int) substr($cutoff, 0, 4))) throw new RuleViolation('erp_cutoff_required', 409);
        if (($account['currency'] ?? null) !== 'IDR') throw new RuleViolation('bank_idr_adapter_required', 409);
        $code = CompanyCoaMapping::where('company_id', $company)->where('semantic_key', 'ppl.bank.'.$account['id'])
            ->where('active', true)->value('account_code');
        if (!$code || !DB::table('accounts')->where('account_code', $code)->exists()) throw new RuleViolation('approved_coa_mapping_required', 409);
        $today = now('Asia/Jakarta')->toDateString();
        $opening = '0.00';
        $movement = '0.00';
        foreach (\App\Support\ProtectedJournalQuery::table('journal_details')->join('journal_headers as h', 'h.journal_id', '=', 'journal_details.journal_id')
            ->where('journal_details.account_code', $code)->whereDate('h.transaction_date', '<=', $today)
            ->select('h.transaction_date', 'journal_details.position', 'journal_details.amount')->cursor() as $line) {
            if (!in_array($line->position, ['DEBET', 'KREDIT'], true)) throw new RuleViolation('invalid_bank_ledger', 409);
            $amount = $line->position === 'DEBET' ? (string) $line->amount : bcsub('0', (string) $line->amount, 2);
            if (substr($line->transaction_date, 0, 10) < $cutoff) $opening = bcadd($opening, $amount, 2);
            else $movement = bcadd($movement, $amount, 2);
        }
        $pending = '0.00';
        foreach (DB::table('ppl_payment_events')->where('company_id', $company)->get() as $event) {
            $snapshot = json_decode($event->snapshot, true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['settlement']['sourceId'] ?? null) !== $account['id']) continue;
            // Never silently overlap pre-cutover v33 debits and ERP opening.
            if ($snapshot['paidDate'] < $cutoff) throw new RuleViolation('precutoff_payment_reconciliation_required', 409);
            if ($snapshot['paidDate'] > $today) throw new RuleViolation('future_payment_event', 409);
            $journalId = $event->journal_id;
            if ($journalId === null) {
                // Crash recovery: committed PostingService evidence can precede application ACK.
                $journalId = DB::table('accounting_posting_intents')->where('idempotency_key', 'ppl:paid:'.$event->id)->whereNotNull('sealed_at')->value('journal_id');
            }
            if ($journalId !== null) {
                $header = \App\Support\ProtectedJournalQuery::table('journal_headers')->where('journal_id', $journalId)->first();
                if (!$header || substr($header->transaction_date, 0, 10) !== $snapshot['paidDate']) throw new RuleViolation('payment_journal_reconciliation_required', 409);
                $debit = '0.00';
                foreach (\App\Support\ProtectedJournalQuery::table('journal_details')->where('journal_id', $journalId)->where('account_code', $code)->get() as $line) {
                    $debit = $line->position === 'KREDIT' ? bcadd($debit, (string) $line->amount, 2) : bcsub($debit, (string) $line->amount, 2);
                }
                if (bccomp($debit, (string) $snapshot['settlement']['debit'], 2) !== 0) throw new RuleViolation('payment_journal_reconciliation_required', 409);
            } else {
                $pending = bcadd($pending, (string) $snapshot['settlement']['debit'], 2);
            }
        }
        $reserved = '0.00';
        foreach (DB::table('ppl_reservations')->where('company_id', $company)->where('account_key', $account['id'])->where('status', 'active')->get() as $reservation) {
            $reserved = bcadd($reserved, (string) $reservation->amount, 2);
        }
        $ledger = bcadd($opening, $movement, 2);
        $actual = bcsub($ledger, $pending, 2);
        return ['account_code' => $code, 'cutoff' => $cutoff, 'opening' => $opening, 'movement' => $movement,
            'ledger' => $ledger, 'unposted_actual_debits' => $pending, 'actual' => $actual, 'reserved' => $reserved,
            'available' => bcsub($actual, $reserved, 2), 'authority' => 'ERP_GL_AND_STABLE_PAYMENT_EVENTS'];
    }
}