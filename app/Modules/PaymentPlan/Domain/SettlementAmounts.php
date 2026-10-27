<?php

namespace App\Modules\PaymentPlan\Domain;

/** Decimal-only settlement arithmetic. Output strings are deliberately exact. */
final class SettlementAmounts
{
    public function calculate(array $input): array
    {
        $values = [];
        foreach (['base', 'vat', 'withholding', 'fees'] as $field) {
            $value = $input[$field] ?? '0';
            if (!is_int($value) && !is_string($value)) throw new RuleViolation('invalid_amount');
            $value = (string) $value;
            if (!preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value) || bccomp($value, '1000000000000', 2) > 0) {
                throw new RuleViolation('invalid_amount');
            }
            $values[$field] = bcadd($value, '0', 2);
        }
        $gross = bcadd($values['base'], $values['vat'], 2);
        if (bccomp($values['base'], '0', 2) <= 0 || bccomp($gross, '1000000000000', 2) > 0
            || bccomp($values['withholding'], $gross, 2) > 0) throw new RuleViolation('invalid_amount');
        $net = bcsub($gross, $values['withholding'], 2);
        return $values + ['gross' => $gross, 'net' => $net, 'debit' => bcadd($net, $values['fees'], 2)];
    }
}