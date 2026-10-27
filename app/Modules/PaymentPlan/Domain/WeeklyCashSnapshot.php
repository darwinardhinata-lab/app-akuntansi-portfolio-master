<?php

namespace App\Modules\PaymentPlan\Domain;

use DateTimeImmutable;
use DateTimeZone;

/** Read-only projection of confirmed petty payments; never reserves or posts money. */
final class WeeklyCashSnapshot
{
    public function calculate(string $start, string $end, array $allRequests, array $settings): array
    {
        $first = $this->date($start);
        $last = $this->date($end);
        if ($last->getTimestamp() - $first->getTimestamp() !== 6 * 86400) throw new RuleViolation('weekly_period');
        $asOf = $settings['cash']['asOf'] ?? null;
        if ($asOf === null || $start < $asOf) throw new RuleViolation('cash_opening');
        $previous = $first->modify('-1 day')->format('Y-m-d');
        $opening = isset($settings['cash']['opening']) ? $this->money($settings['cash']['opening']) : null;
        $receipts = [];
        $payments = [];
        $incoming = '0.00';
        $outgoing = '0.00';
        foreach ($settings['cashReceipts'] ?? [] as $receipt) {
            $amount = $this->money($receipt['amount']);
            if ($opening !== null && $receipt['date'] >= $asOf && $receipt['date'] <= $previous) $opening = bcadd($opening, $amount, 2);
            if ($receipt['date'] >= $start && $receipt['date'] <= $end) {
                $receipts[] = $receipt;
                $incoming = bcadd($incoming, $amount, 2);
            }
        }
        foreach ($allRequests as $request) {
            if ($request['route'] !== 'petty' || !in_array($request['status'], ['paid', 'posted'], true)) continue;
            $amount = $this->money($request['settlement']['debit']);
            if ($opening !== null && $request['paidDate'] >= $asOf && $request['paidDate'] <= $previous) $opening = bcsub($opening, $amount, 2);
            if ($request['paidDate'] < $start || $request['paidDate'] > $end) continue;
            $payments[] = [
                'id' => $request['id'], 'doc' => $request['doc'], 'date' => $request['paidDate'],
                'department' => $request['department'], 'payee' => $request['payee'], 'purpose' => $request['purpose'],
                'amount' => $amount, 'category' => $request['category'],
                'attachments' => array_values(array_filter($request['attachments'], fn ($a) => $a['kind'] === 'payment')),
            ];
            $outgoing = bcadd($outgoing, $amount, 2);
        }
        return compact('start', 'end', 'opening', 'receipts', 'payments', 'incoming', 'outgoing')
            + ['closing' => bcsub(bcadd($opening ?? '0', $incoming, 2), $outgoing, 2)];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d') !== $value) throw new RuleViolation('invalid_date');
        return $date;
    }

    private function money(mixed $value): string
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value)) throw new RuleViolation('invalid_amount');
        return bcadd((string) $value, '0', 2);
    }
}