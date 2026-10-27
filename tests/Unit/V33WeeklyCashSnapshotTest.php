<?php

namespace Tests\Unit;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Domain\WeeklyCashSnapshot;
use PHPUnit\Framework\TestCase;

class V33WeeklyCashSnapshotTest extends TestCase
{
    public function test_golden_weekly_cases_have_explicit_transaction_context(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-weekly-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture['cases'] as $case) {
            try {
                $actual = (new WeeklyCashSnapshot())->calculate($case['start'], $case['end'], $case['allRequests'], $case['settings']);
                $this->assertArrayNotHasKey('error', $case['expect']);
                foreach ($case['expect'] as $field => $value) $this->assertSame(0, bccomp((string) $value, $actual[$field], 2));
            } catch (RuleViolation $exception) {
                $this->assertSame($case['expect']['error'] ?? null, $exception->rule);
            }
        }
    }

    public function test_only_confirmed_petty_cash_enters_snapshot_and_previous_period_carries_forward(): void
    {
        $base = ['id' => 'one', 'doc' => 'PP-ONE', 'department' => 'office', 'payee' => 'Synthetic', 'purpose' => ['A', 'B', 'C'],
            'category' => 'office', 'route' => 'petty', 'status' => 'paid', 'paidDate' => '2026-02-28',
            'settlement' => ['debit' => '0.10'], 'attachments' => [['kind' => 'payment'], ['kind' => 'request']]];
        $requests = [$base, array_replace($base, ['id' => 'two', 'paidDate' => '2026-03-02']),
            array_replace($base, ['id' => 'pending', 'status' => 'petty_approved', 'paidDate' => '2026-03-02']),
            array_replace($base, ['id' => 'bank', 'route' => 'bank', 'paidDate' => '2026-03-02'])];
        $settings = ['cash' => ['opening' => '1.00', 'asOf' => '2026-01-01'],
            'cashReceipts' => [['date' => '2026-02-28', 'amount' => '0.20'], ['date' => '2026-03-01', 'amount' => '0.30']]];
        $actual = (new WeeklyCashSnapshot())->calculate('2026-03-01', '2026-03-07', $requests, $settings);
        $this->assertSame('1.10', $actual['opening']);
        $this->assertSame('0.30', $actual['incoming']);
        $this->assertSame('0.10', $actual['outgoing']);
        $this->assertSame('1.30', $actual['closing']);
        $this->assertCount(1, $actual['payments']);
        $this->assertCount(1, $actual['payments'][0]['attachments']);
    }
}