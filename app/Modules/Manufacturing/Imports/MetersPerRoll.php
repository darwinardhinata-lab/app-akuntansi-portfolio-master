<?php

namespace App\Modules\Manufacturing\Imports;

use InvalidArgumentException;

final class MetersPerRoll
{
    public static function parse(mixed $value, int $row): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Match DECIMAL(20,2) without casting through float or silently rounding.
        if (! preg_match('/\A[0-9]+(?:[.,][0-9]+)?\z/', $value)) {
            throw new InvalidArgumentException("Baris {$row}: meter per gulung harus berupa desimal positif.");
        }

        [$whole, $fraction] = array_pad(preg_split('/[.,]/', $value), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        if (strlen($whole) > 18 || strlen($fraction) > 2 || ($whole === '0' && $fraction === '')) {
            throw new InvalidArgumentException("Baris {$row}: meter per gulung harus lebih besar dari nol dan dapat disimpan tanpa pembulatan (maks. 18 digit bulat dan 2 digit desimal).");
        }

        return $whole.($fraction === '' ? '' : '.'.$fraction);
    }
}