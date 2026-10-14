<?php

// FIX: multicurrency — operasi uang string/bcmath dengan pembulatan HALF_UP deterministik.
namespace App\Modules\Currency\Support;

use InvalidArgumentException;

class Money
{
    private static function digits(string $value): int
    {
        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('Nilai uang harus string desimal tanpa notasi eksponen.');
        }

        return str_contains($value, '.') ? strlen(explode('.', $value, 2)[1]) : 0;
    }

    public static function round(string $value, int $scale = 2): string
    {
        $digits = self::digits($value);
        if ($scale < 0 || $scale > 20) {
            throw new InvalidArgumentException('Presisi uang harus antara 0 dan 20.');
        }
        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');
        $half = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';
        $result = bcadd($absolute, $half, max($digits, $scale + 1));
        $result = bcadd($result, '0', $scale);

        return $negative && bccomp($result, '0', $scale) !== 0 ? '-'.$result : $result;
    }

    public static function add(string $a, string $b, int $scale = 2): string
    {
        return self::round(bcadd($a, $b, max(self::digits($a), self::digits($b))), $scale);
    }

    public static function sub(string $a, string $b, int $scale = 2): string
    {
        return self::round(bcsub($a, $b, max(self::digits($a), self::digits($b))), $scale);
    }

    public static function mul(string $a, string $b, int $scale = 2): string
    {
        return self::round(bcmul($a, $b, self::digits($a) + self::digits($b)), $scale);
    }

    public static function div(string $a, string $b, int $scale = 2): string
    {
        self::digits($a);
        self::digits($b);

        return self::round(bcdiv($a, $b, $scale + 1), $scale);
    }

    public static function convert(string $txn, string $rate, int $scale = 2): string
    {
        return self::mul($txn, $rate, $scale);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, max(self::digits($a), self::digits($b)));
    }
}