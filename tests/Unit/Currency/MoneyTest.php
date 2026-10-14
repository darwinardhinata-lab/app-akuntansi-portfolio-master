<?php

// FIX: multicurrency — pembuktian presisi tanpa float untuk fondasi konversi.
namespace Tests\Unit\Currency;

use App\Modules\Currency\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_half_up_and_currency_precision(): void
    {
        $this->assertSame('2.68', Money::round('2.675'));
        $this->assertSame('-2.68', Money::round('-2.675'));
        $this->assertSame('3', Money::round('2.5', 0));
        $this->assertSame('0.00', Money::round('-0.001'));
        $this->assertSame('100000000000000000000.00', Money::round('99999999999999999999.995'));
    }

    public function test_operations_and_conversion_preserve_precision(): void
    {
        $this->assertSame('0.30', Money::add('0.1', '0.2'));
        $this->assertSame('0.10', Money::sub('0.3', '0.2'));
        $this->assertSame('15800000.00', Money::convert('1000.00', '15800.00000000'));
        $this->assertSame('113.79640000', Money::div('11379.64', '100', 8));
        $this->assertSame('0.67', Money::div('2', '3'));
        $this->assertSame(1, Money::compare('1.000000001', '1.000000000'));
        $this->assertSame(0, Money::compare('0.1', '0.10'));
    }

    public function test_scientific_notation_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::round('1e3');
    }
}