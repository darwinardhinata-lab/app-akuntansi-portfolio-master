<?php

namespace App\Modules\PaymentPlan\Domain;

use Closure;
use DateTimeImmutable;

/** Pure v33 approval slice. No database, HTTP identity, balances or posting side effects. */
final class V33ApprovalDomain
{
    public function __construct(private readonly Closure $clock)
    {
    }

    public function transition(array $request, string $action, array $input, array $actor, array $allRequests = [], array $settings = []): array
    {
        if (!$this->canSee($actor, $request)) throw new RuleViolation('forbidden', 403);
        $expected = match ($action) {
            'approve_request' => ['submitted'],
            'approve_settlement' => ['settlement_review'],
            'ack_manager', 'ack_gm' => ['petty_review'],
            'hold_bank' => ['bank_prepared'],
            'restart_after_no_debit' => ['bank_hold'],
            'post_external' => ['paid'],
            'reject' => ['submitted', 'petty_review'],
            'bank_attempt' => ['settlement_approved', 'bank_prepared', 'bank_released'],
            'correction' => ['paid', 'posted'],
            'submit' => ['draft'],
            'mark_maker' => ['settlement_approved'],
            'release_gm' => ['bank_prepared'],
            'confirm_paid' => ['bank_released', 'petty_approved'],
            'prepare_settlement' => ['request_approved'],
            'approve_petty' => ['petty_review'],
            'return' => ['submitted', 'request_approved', 'settlement_review', 'settlement_approved', 'petty_review'],
            default => throw new RuleViolation('invalid_action'),
        };
        if (!in_array($request['status'], $expected, true)) throw new RuleViolation('wrong_stage', 409);
        $roles = match ($action) {
            'approve_request', 'approve_settlement' => ['director'],
            'ack_manager' => ['division_manager'], 'ack_gm', 'hold_bank' => ['general_manager'],
            'restart_after_no_debit' => ['finance_manager'], 'post_external' => ['accountant'],
            'reject' => [$request['route'] === 'bank' ? 'director' : 'finance_manager'],
            'bank_attempt' => ['maker', 'finance_staff', 'finance_manager'],
            'correction' => ['finance_manager', 'accountant'],
            'submit' => $actor['roles'],
            'mark_maker' => ['maker'], 'release_gm' => ['general_manager'],
            'confirm_paid' => ['finance_staff', 'finance_manager'],
            'prepare_settlement' => ['finance_staff', 'finance_manager'],
            'approve_petty' => ['finance_manager'],
            'return' => $request['route'] === 'petty' && $request['status'] === 'petty_review'
                ? ['finance_manager', 'general_manager', 'division_manager'] : ['director', 'finance_manager', 'finance_staff'],
        };
        // v33 checks role before independence for bank, independence first for petty.
        $independent = in_array($action, ['approve_request', 'approve_settlement', 'approve_petty', 'ack_manager', 'ack_gm', 'hold_bank', 'reject'], true);
        if (in_array($action, ['ack_manager', 'ack_gm'], true) && $actor['id'] === $request['createdBy']) throw new RuleViolation('self_approval', 403);
        if (!array_intersect($roles, $actor['roles'])) throw new RuleViolation('role_denied', 403);
        if ($independent && $actor['id'] === $request['createdBy']) throw new RuleViolation('self_approval', 403);
        $instant = ($this->clock)();
        if (!$instant instanceof DateTimeImmutable) throw new \LogicException('Clock must return DateTimeImmutable.');
        $decision = [
            'action' => $action, 'actorId' => $actor['id'], 'actor' => $actor['name'], 'email' => $actor['email'],
            'at' => $instant->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'reference' => mb_substr(trim((string) ($input['reference'] ?? '')), 0, 500),
        ];
        $note = mb_substr(trim((string) ($input['reason'] ?? '')), 0, 2000);
        $balances = new PaymentBalances();
        if ($action === 'return') {
            if ($request['route'] === 'petty' && $request['status'] === 'petty_review'
                && in_array('division_manager', $actor['roles'], true)
                && !array_intersect(['finance_manager', 'general_manager'], $actor['roles'])
                && $actor['department'] !== $request['department']) throw new RuleViolation('department_denied', 403);
            if ($note === '') throw new RuleViolation('reason_required');
            $archive = ['at' => $decision['at'], 'attachments' => $request['attachments']];
            foreach (['requestApproval', 'settlement', 'balanceSnapshot', 'settlementApproval', 'managerAcknowledgement', 'gmAcknowledgement'] as $field) {
                if (array_key_exists($field, $request)) $archive[$field] = $request[$field];
            }
            $request['archivedVersions'][] = $archive;
            $fields = ['settlement', 'settlementApproval', 'balanceSnapshot'];
            if ($request['status'] === 'settlement_review') $request['status'] = 'request_approved';
            else {
                $request['status'] = 'draft';
                $fields = array_merge($fields, ['requestApproval', 'managerAcknowledgement', 'gmAcknowledgement', 'pettyApproval']);
            }
            foreach ($fields as $field) $request[$field] = null;
        } elseif ($action === 'approve_petty' || $action === 'prepare_settlement') {
            if ($action === 'approve_petty') {
                if (empty($request['managerAcknowledgement']) || empty($request['gmAcknowledgement'])) throw new RuleViolation('petty_acknowledgements');
                if (in_array($actor['id'], [$request['managerAcknowledgement']['actorId'], $request['gmAcknowledgement']['actorId']], true)) throw new RuleViolation('role_separation', 403);
            }
            if (empty($input['checked'])) throw new RuleViolation('confirmation_required');
            if ($action === 'prepare_settlement' && !array_filter($request['attachments'], fn ($a) => $a['kind'] === 'settlement')) throw new RuleViolation('invoice_attachment');
            $financials = (new SettlementAmounts())->calculate($input);
            if ($action === 'approve_petty' && bccomp($financials['fees'], '0', 2) !== 0) throw new RuleViolation('cash_fee');
            $coa = trim((string) ($input['coa'] ?? ''));
            if ($action === 'prepare_settlement' && !preg_match('/\A\d{6}\z/', $coa)) throw new RuleViolation('invalid_coa');
            if (bccomp($financials['gross'], (string) $request['amount'], 2) !== 0 && $note === '') throw new RuleViolation('variance_reason');
            if (!preg_match('/\A\d{6}\z/', $coa)) throw new RuleViolation('invalid_coa');
            $balances->allocation($request, $allRequests, $financials['gross']);
            $settlement = $financials + ['sourceId' => 'petty', 'coa' => $coa, 'varianceReason' => $note,
                'preparedBy' => $actor['id'], 'preparedName' => $actor['name'], 'preparedAt' => $decision['at']];
            if ($action === 'approve_petty') {
                $cash = $balances->cash($allRequests, $settings);
                if ($cash === null || bccomp($financials['debit'], $cash, 2) > 0) throw new RuleViolation('cash_insufficient');
                $request['pettyApproval'] = $decision;
                $request['status'] = 'petty_approved';
            } else {
                $sourceRequest = $request;
                $sourceRequest['settlement']['sourceId'] = $input['sourceId'] ?? '';
                $account = $balances->source($sourceRequest, $settings);
                if ($balances->account($account, $allRequests, $settings) === null) throw new RuleViolation('balance_required');
                $invoice = mb_substr(trim((string) (($input['invoiceNumber'] ?? '') ?: $request['invoice'])), 0, 100);
                if ($request['category'] === 'raw_ap' && $invoice !== $request['invoice']) throw new RuleViolation('invoice_required');
                $stageEvidence = mb_substr(trim((string) ($input['stageEvidence'] ?? '')), 0, 500);
                if ($request['category'] === 'asset' && $request['stage'] !== 'dp' && $stageEvidence === '') throw new RuleViolation('stage_evidence');
                $settlement['sourceId'] = $account['id'];
                $settlement += ['sourceName' => $account['name'], 'sourceNumber' => $account['number'],
                    'invoiceMatch' => bccomp($financials['gross'], (string) $request['amount'], 2) === 0,
                    'invoiceNumber' => $invoice, 'taxNotes' => mb_substr(trim((string) ($input['taxNotes'] ?? '')), 0, 1000), 'stageEvidence' => $stageEvidence];
                $request['balanceSnapshot'] = $balances->snapshot($request, $financials['debit'], $account, $allRequests, $settings, $decision['at'], $instant);
                $request['status'] = 'settlement_review';
            }
            $request['settlement'] = $settlement;
        } elseif ($action === 'submit') {
            if ($actor['id'] !== $request['createdBy']) throw new RuleViolation('forbidden', 403);
            if (in_array($request['category'], ['asset', 'raw_dp'], true) && empty($request['po'])) throw new RuleViolation('po_required');
            if ($request['category'] === 'raw_ap' && empty($request['invoice'])) throw new RuleViolation('invoice_required');
            if ($request['category'] === 'asset' && empty($request['capex'])) throw new RuleViolation('capex_required');
            if (empty($request['recipientAccount']) && $request['route'] === 'bank') throw new RuleViolation('bank_required');
            $balances->allocation($request, $allRequests, (string) $request['amount']);
            $request['status'] = $request['route'] === 'petty' ? 'petty_review' : 'submitted';
        } elseif ($action === 'mark_maker') {
            if (in_array($actor['id'], [$request['createdBy'], $request['settlementApproval']['actorId']], true)) throw new RuleViolation('role_separation', 403);
            if ($decision['reference'] === '') throw new RuleViolation('bank_reference');
            $account = $balances->source($request, $settings);
            $balance = $balances->account($account, $allRequests, $settings);
            if ($balance === null || bccomp($balance, (string) $request['settlement']['debit'], 2) < 0) throw new RuleViolation('funds_insufficient');
            $request['bankMaker'] = $decision;
            $request['status'] = 'bank_prepared';
        } elseif ($action === 'release_gm') {
            $balances->source($request, $settings);
            if (in_array($actor['id'], [$request['bankMaker']['actorId'], $request['createdBy']], true)) throw new RuleViolation('role_separation', 403);
            if (empty($input['checked']) || $decision['reference'] === '') throw new RuleViolation('bank_reference');
            $request['bankRelease'] = $decision;
            $request['status'] = 'bank_released';
        } elseif ($action === 'confirm_paid') {
            $separated = $request['route'] === 'petty' ? [$request['pettyApproval']['actorId']] : [$request['bankMaker']['actorId'], $request['bankRelease']['actorId']];
            if (in_array($actor['id'], $separated, true)) throw new RuleViolation('role_separation', 403);
            $date = $input['paidDate'] ?? '';
            $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            $approval = $request['route'] === 'bank' ? $request['bankRelease'] : $request['pettyApproval'];
            $zone = new \DateTimeZone('Asia/Jakarta');
            $minimum = (new DateTimeImmutable($approval['at']))->setTimezone($zone)->format('Y-m-d');
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > $instant->setTimezone($zone)->format('Y-m-d') || $date < $minimum) throw new RuleViolation('invalid_date');
            $attached = array_filter($request['attachments'], fn ($a) => $a['kind'] === 'payment');
            if (empty($input['checked']) || $decision['reference'] === '' || !$attached) throw new RuleViolation('payment_evidence');
            $balances->allocation($request, $allRequests, (string) $request['settlement']['gross']);
            $cash = $balances->cash($allRequests, $settings);
            if ($request['route'] === 'petty' && ($cash === null || bccomp($cash, (string) $request['settlement']['debit'], 2) < 0)) throw new RuleViolation('cash_insufficient');
            $request['paidDate'] = $date;
            $request['paymentConfirmation'] = $decision;
            $request['status'] = 'paid';
        } elseif ($action === 'approve_request') {
            if (empty($input['checked'])) throw new RuleViolation('confirmation_required');
            $request['requestApproval'] = $decision;
            $request['requestCeiling'] = $request['amount'];
            $request['status'] = 'request_approved';
        } elseif ($action === 'ack_manager') {
            if ($actor['department'] !== $request['department']) throw new RuleViolation('department_denied', 403);
            if (($request['gmAcknowledgement']['actorId'] ?? null) === $actor['id']) throw new RuleViolation('role_separation', 403);
            $request['managerAcknowledgement'] = $decision;
        } elseif ($action === 'ack_gm') {
            if (($request['managerAcknowledgement']['actorId'] ?? null) === $actor['id']) throw new RuleViolation('role_separation', 403);
            $request['gmAcknowledgement'] = $decision;
        } elseif ($action === 'approve_settlement') {
            if ($actor['id'] === $request['settlement']['preparedBy']) throw new RuleViolation('role_separation', 403);
            if (empty($input['checked'])) throw new RuleViolation('confirmation_required');
            if (bccomp((string) $request['settlement']['gross'], (string) $request['requestCeiling'], 2) > 0 && empty($input['revisedCeiling'])) {
                throw new RuleViolation('new_ceiling_confirmation');
            }
            $request['settlementApproval'] = $decision + ['ceiling' => $request['settlement']['gross'], 'debitCeiling' => $request['settlement']['debit']];
            $request['status'] = 'settlement_approved';
        } elseif ($action === 'hold_bank') {
            if ($note === '' || $decision['reference'] === '' || empty($input['checked'])) throw new RuleViolation('reason_required');
            $request['bankHold'] = $decision + ['reason' => $note];
            $request['status'] = 'bank_hold';
        } elseif ($action === 'restart_after_no_debit') {
            $attached = array_filter($request['attachments'], fn ($attachment) => $attachment['kind'] === 'payment');
            if (empty($input['checked']) || $decision['reference'] === '' || $note === '' || !$attached) throw new RuleViolation('payment_evidence');
            $archive = $request;
            $archive['history'] = [];
            $archive['archivedVersions'] = [];
            $request['archivedVersions'][] = $archive;
            $request['status'] = 'draft';
            foreach (['requestApproval', 'settlement', 'settlementApproval', 'balanceSnapshot', 'bankMaker', 'bankRelease', 'bankHold'] as $field) $request[$field] = null;
        } elseif ($action === 'post_external') {
            if (in_array($actor['id'], [$request['bankMaker']['actorId'] ?? null, $request['paymentConfirmation']['actorId'] ?? null], true)) throw new RuleViolation('role_separation', 403);
            if (empty($input['checked']) || $decision['reference'] === '') throw new RuleViolation('journal_required');
            foreach ($allRequests as $other) {
                if ($other['id'] !== $request['id'] && ($other['journal']['reference'] ?? null) === $decision['reference']) throw new RuleViolation('duplicate_journal');
            }
            // Legacy domain parity only: the ERP adapter must obtain a real PostingService acknowledgement.
            $request['journal'] = $decision;
            $request['status'] = 'posted';
        } elseif ($action === 'reject') {
            if ($note === '') throw new RuleViolation('reason_required');
            $request['status'] = 'rejected';
        } elseif ($action === 'bank_attempt') {
            if (!in_array($input['result'] ?? null, ['pending', 'failed'], true) || $decision['reference'] === '' || empty($input['checked'])) throw new RuleViolation('bank_reference');
            $request['attempts'][] = $decision + ['result' => $input['result'], 'reason' => $note];
        } elseif ($action === 'correction') {
            if ($note === '' || $decision['reference'] === '') throw new RuleViolation('reason_required');
            $request['corrections'][] = $decision + ['reason' => $note];
        }
        $request['decisions'][] = $decision + ['reason' => mb_substr(trim((string) ($input['reason'] ?? '')), 0, 2000)];
        return $request;
    }

    public function canSee(array $actor, array $request): bool
    {
        return (bool) array_intersect($actor['roles'], ['admin', 'finance_staff', 'finance_manager', 'director', 'maker', 'general_manager', 'accountant'])
            || $request['createdBy'] === $actor['id']
            || (in_array('division_manager', $actor['roles'], true) && $actor['department'] === $request['department']);
    }
}