<?php

namespace App\Modules\PaymentPlan\Domain;

/** Legacy balance/allocation projections; reservations are a separate hardened policy. */
final class PaymentBalances
{
    public function account(array $account, array $all, array $settings): ?string
    {
        if (!isset($account['opening']) || empty($account['asOf'])) return null;
        $balance = (string) $account['opening'];
        foreach ($settings['receipts'] ?? [] as $receipt) {
            if ($receipt['accountId'] === $account['id'] && $receipt['date'] >= $account['asOf']) $balance = bcadd($balance, (string) $receipt['amount'], 2);
        }
        foreach ($all as $request) {
            if ($this->paid($request) && $request['route'] === 'bank' && ($request['settlement']['sourceId'] ?? null) === $account['id'] && $request['paidDate'] >= $account['asOf']) {
                $balance = bcsub($balance, (string) $request['settlement']['debit'], 2);
            }
        }
        return bcadd($balance, '0', 2);
    }

    public function cash(array $all, array $settings): ?string
    {
        if (!isset($settings['cash']['opening']) || empty($settings['cash']['asOf'])) return null;
        $account = ['id' => 'petty', 'opening' => $settings['cash']['opening'], 'asOf' => $settings['cash']['asOf']];
        $balance = (string) $account['opening'];
        foreach ($settings['cashReceipts'] ?? [] as $receipt) {
            if ($receipt['date'] >= $account['asOf']) $balance = bcadd($balance, (string) $receipt['amount'], 2);
        }
        foreach ($all as $request) {
            if ($this->paid($request) && $request['route'] === 'petty' && $request['paidDate'] >= $account['asOf']) $balance = bcsub($balance, (string) $request['settlement']['debit'], 2);
        }
        return bcadd($balance, '0', 2);
    }

    public function residual(array $request, array $all): ?string
    {
        if (!in_array($request['category'], ['asset', 'raw_ap'], true)) return null;
        $field = $request['category'] === 'asset' ? 'po' : 'invoice';
        if (empty($request[$field]) || empty($request['referenceTotal'])) return null;
        $remaining = bcsub((string) $request['referenceTotal'], (string) $request['priorPaid'], 2);
        foreach ($all as $other) {
            if ($other['id'] === $request['id'] || !$this->paid($other) || $other['currency'] !== $request['currency']
                || $other['category'] !== $request['category'] || mb_strtolower($other['payee']) !== mb_strtolower($request['payee'])
                || ($other[$field] ?? null) !== $request[$field]) continue;
            if (bccomp((string) $other['referenceTotal'], (string) $request['referenceTotal'], 2) !== 0
                || bccomp((string) $other['priorPaid'], (string) $request['priorPaid'], 2) !== 0) throw new RuleViolation('reference_conflict');
            $remaining = bcsub($remaining, (string) ($other['settlement']['gross'] ?? 0), 2);
        }
        return $remaining;
    }

    public function allocation(array $request, array $all, string $amount): void
    {
        $remaining = $this->residual($request, $all);
        if (in_array($request['category'], ['asset', 'raw_ap'], true) && ($remaining === null || bccomp($amount, $remaining, 2) > 0)) throw new RuleViolation('overpayment');
    }

    public function source(array $request, array $settings): array
    {
        foreach ($settings['accounts'] as $account) {
            if ($account['id'] !== $request['settlement']['sourceId']) continue;
            $currency = ['idr3' => 'IDR', 'usd1' => 'USD'][$account['id']] ?? null;
            if ($currency !== null && $currency === $account['currency'] && !empty($account['payment']) && $account['currency'] === $request['currency']) return $account;
        }
        throw new RuleViolation('source_account');
    }

    public function snapshot(array $request, string $debit, array $source, array $all, array $settings, string $at, \DateTimeImmutable $instant): array
    {
        $accounts = [];
        foreach ($settings['accounts'] as $account) {
            $paid = '0.00';
            $received = '0.00';
            foreach ($all as $other) {
                if ($this->paid($other) && $other['route'] === 'bank' && ($other['settlement']['sourceId'] ?? null) === $account['id'] && $other['paidDate'] >= ($account['asOf'] ?? '')) $paid = bcadd($paid, (string) $other['settlement']['debit'], 2);
            }
            foreach ($settings['receipts'] ?? [] as $receipt) {
                if ($receipt['accountId'] === $account['id'] && $receipt['date'] >= ($account['asOf'] ?? '')) $received = bcadd($received, (string) $receipt['amount'], 2);
            }
            $accounts[] = $account + ['current' => $this->account($account, $all, $settings), 'totalPaid' => $paid, 'totalReceived' => $received];
        }
        $payments = [];
        $today = $instant->setTimezone(new \DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        foreach ($all as $other) {
            if ($other['route'] !== 'bank' || in_array($other['status'], ['draft', 'rejected'], true) || ($this->paid($other) && $other['paidDate'] !== $today)) continue;
            $payments[] = ['id' => $other['id'], 'doc' => $other['doc'], 'date' => $other['requestDate'], 'payee' => $other['payee'],
                'purpose' => $other['purpose'], 'currency' => $other['currency'], 'amount' => $other['settlement']['debit'] ?? $other['amount'],
                'status' => $other['status'], 'sourceId' => $other['settlement']['sourceId'] ?? '', 'paidDate' => $other['paidDate'] ?? ''];
        }
        return ['at' => $at, 'accounts' => $accounts, 'payments' => $payments,
            'thisPayment' => ['id' => $request['id'], 'doc' => $request['doc'], 'payee' => $request['payee'], 'amount' => $debit, 'currency' => $request['currency'], 'sourceId' => $source['id']],
            'settingsVersion' => $settings['version']];
    }

    private function paid(array $request): bool
    {
        return in_array($request['status'], ['paid', 'posted'], true);
    }
}