<?php

namespace App\Modules\PaymentPlan\Domain;

final class RuleViolation extends \RuntimeException
{
    public function __construct(public readonly string $rule, public readonly int $httpStatus = 422)
    {
        parent::__construct($rule);
    }
}