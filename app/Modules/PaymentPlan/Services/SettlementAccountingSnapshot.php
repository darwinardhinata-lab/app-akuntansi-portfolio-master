<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use Illuminate\Support\Facades\DB;

/** Server-owned approval evidence; never accept posting lines from the browser. */
final class SettlementAccountingSnapshot
{
    public function capture(array $request, int $company): array
    {
        if (DB::transactionLevel() === 0) throw new RuleViolation('ap_transaction_required', 409);
        $settlement = $request['settlement'];
        $context = DB::table('ppl_financial_contexts')->where('company_id', $company)->lockForUpdate()->first();
        $settings = $context ? json_decode($context->settings, true, 512, JSON_THROW_ON_ERROR) : [];
        $account = collect($settings['accounts'] ?? [])->firstWhere('id', $settlement['sourceId']);
        if (!$account || ($account['currency'] ?? null) !== $request['currency']) throw new RuleViolation('financial_configuration_required', 409);
        $source = null;
        $lines = [];
        $entries = [['expense', 'DEBET', $settlement['base']], ['vat', 'DEBET', $settlement['vat']]];
        if ($request['category'] === 'raw_ap') {
            $source = (new PurchaseBillAllocation())->source((int) $request['purchaseBillId'], $company);
            if (isset($source['blocked'])) throw new RuleViolation('ap_reconciliation_required', 409);
            unset($source['residual'], $source['events'], $source['exceptions']); // Availability is checked again under lock.
            $lines[] = ['account_code' => $source['account_code'], 'position' => 'DEBET', 'amount' => (string) $settlement['gross']];
            $entries = [];
        }
        $mappings = [];
        foreach (array_merge($entries, [['fees', 'DEBET', $settlement['fees']], ['withholding', 'KREDIT', $settlement['withholding']],
            ['bank.'.$settlement['sourceId'], 'KREDIT', $settlement['debit']]]) as [$semantic, $side, $amount]) {
            if (bccomp((string) $amount, '0', 2) === 0) continue;
            $mapping = DB::table('company_coa_mappings')->where('company_id', $company)->where('semantic_key', 'ppl.'.$semantic)->lockForUpdate()->first();
            if (!$mapping || !$mapping->active || !DB::table('accounts')->where('account_code', $mapping->account_code)->exists()) throw new RuleViolation('approved_coa_mapping_required', 409);
            if ($semantic === 'expense' && $mapping->account_code !== $settlement['coa']) throw new RuleViolation('settlement_coa_mapping_conflict', 409);
            // No mapping revision column exists: fingerprint its persisted identity/content/timestamp.
            $mappings[$semantic] = (array) $mapping;
            $lines[] = ['account_code' => $mapping->account_code, 'position' => $side, 'amount' => (string) $amount];
        }
        return ['company_id' => $company, 'currency' => $request['currency'], 'category' => $request['category'],
            'settlement' => $settlement, 'source' => $source, 'source_account' => array_intersect_key($account, array_flip(['id', 'number', 'name', 'currency', 'payment'])),
            'settings_version' => $settings['version'] ?? null, 'mappings' => $mappings, 'lines' => $lines];
    }

    public function validateReservation(array $request, int $company): void
    {
        $approved = $request['accountingApproval']['basis'] ?? null;
        if (!$approved || $approved !== $this->capture($request, $company)) {
            throw new RuleViolation('settlement_reapproval_required', 409);
        }
    }
}