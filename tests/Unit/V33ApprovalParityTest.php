<?php

namespace Tests\Unit;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Domain\V33ApprovalDomain;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class V33ApprovalParityTest extends TestCase
{
    public static function cases(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-approval-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        foreach ($fixture['cases'] as $case) {
            $actor = $case['actor'];
            foreach ($fixture['actors'] as $candidate) {
                if ($candidate['id'] === $actor['id']) $actor += $candidate;
            }
            $result[$case['id']] = [$case, $actor, $fixture['provenance']['clock']];
        }
        return $result;
    }

    #[DataProvider('cases')]
    public function test_explicit_legacy_approval_contract(array $case, array $actor, string $clock): void
    {
        $domain = new V33ApprovalDomain(fn () => new DateTimeImmutable($clock));
        $original = $case['given'];
        try {
            $actual = $domain->transition($original, $case['action'], $case['input'], $actor, $case['allRequests'], $case['settings']);
            $this->assertArrayNotHasKey('error', $case['expect']);
            $this->assertSame($case['expect']['status'], $actual['status']);
            $decision = $actual['decisions'][array_key_last($actual['decisions'])];
            $this->assertSame($clock, $decision['at']);
            $this->assertSame($actor['id'], $decision['actorId']);
            $this->assertSame($case['given'], $original);
            foreach ($case['expect']['settlement'] ?? [] as $field => $expected) {
                $this->assertEquals($expected, $actual['settlement'][$field]);
            }
            if ($case['action'] === 'approve_request') {
                $this->assertSame($original['amount'], $actual['requestCeiling']);
            }
            $oracle = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-reference-projections.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertEquals($this->canonical($oracle['projections'][$case['id']]), $this->canonical($actual), 'Full reference projection '.$case['id']);
        } catch (RuleViolation $exception) {
            $this->assertSame($case['expect']['error'] ?? null, $exception->rule);
            $this->assertSame($case['expect']['http'] ?? null, $exception->httpStatus);
            $this->assertSame($case['given'], $original);
        }
    }

    private function canonical(mixed $value): mixed
    {
        if (is_array($value)) return array_map(fn ($item) => $this->canonical($item), $value);
        if ((is_int($value) || is_float($value) || is_string($value)) && is_numeric($value)) return bcadd((string) $value, '0', 2);
        return $value;
    }
}