<?php

namespace App\Services;

use App\Models\Account;
use App\Support\JournalBalanceValidator;

class PurchaseReturnValuationService
{
    public function journalDetails(string $journalId, string $payableAccount, float $refund, float $carryingValue): array
    {
        $refund = round($refund, 2);
        $carryingValue = round($carryingValue, 2);
        $variance = round($carryingValue - $refund, 2);
        $inventoryAccount = config('coa.persediaan');
        $varianceAccount = config('coa.selisih_retur_pembelian');
        $accounts = [$payableAccount, $inventoryAccount];
        if ($variance != 0.0) {
            $accounts[] = $varianceAccount;
        }
        foreach ($accounts as $account) {
            if (!$account || !Account::where('account_code', $account)->exists()) {
                throw new \RuntimeException(__('erp.audit_purchase_return_valuation_guard'));
            }
        }
        if ($refund <= 0 || $carryingValue < 0
            || ($variance != 0.0 && in_array($varianceAccount, [$payableAccount, $inventoryAccount], true))) {
            throw new \RuntimeException(__('erp.audit_purchase_return_valuation_guard'));
        }
        $line = fn ($account, $position, $amount) => [
            'journal_id' => $journalId, 'account_code' => $account,
            'position' => $position, 'amount' => $amount, 'helper_code' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $details = [$line($payableAccount, 'DEBET', $refund)];
        if ($carryingValue > 0) {
            $details[] = $line($inventoryAccount, 'KREDIT', $carryingValue);
        }
        if ($variance != 0.0) {
            $details[] = $line($varianceAccount, $variance > 0 ? 'DEBET' : 'KREDIT', abs($variance));
        }
        if (!JournalBalanceValidator::isBalanced($details)) {
            throw new \RuntimeException(__('erp.audit_purchase_return_valuation_guard'));
        }
        return $details;
    }
}