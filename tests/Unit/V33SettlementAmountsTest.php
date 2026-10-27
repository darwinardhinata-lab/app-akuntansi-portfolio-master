<?php

namespace Tests\Unit;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Domain\SettlementAmounts;
use PHPUnit\Framework\TestCase;

class V33SettlementAmountsTest extends TestCase
{
    public function test_golden_financial_subset_with_exact_decimal_output(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-financial-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture['cases'] as $case) {
            $actual = (new SettlementAmounts())->calculate($case['input']);
            foreach ($case['expect'] as $field => $expected) {
                $this->assertSame(0, bccomp((string) $expected, $actual[$field], 2), $field);
            }
        }
    }

    public function test_hardened_decimal_contract_rejects_float_and_fractional_cents(): void
    {
        foreach (['1.001', '1e3', '-1', 1.25] as $value) {
            try {
                (new SettlementAmounts())->calculate(['base' => $value]);
                $this->fail('Noncanonical money accepted.');
            } catch (RuleViolation $exception) {
                $this->assertSame('invalid_amount', $exception->rule);
            }
        }
        $actual = (new SettlementAmounts())->calculate(['base' => '0.10', 'vat' => '0.20', 'fees' => '0.01']);
        $this->assertSame('0.31', $actual['debit']);
    }
}