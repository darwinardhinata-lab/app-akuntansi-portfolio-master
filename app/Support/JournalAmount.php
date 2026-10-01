<?php

namespace App\Support;

use InvalidArgumentException;

class JournalAmount
{
    /**
     * Convert a journal amount to a fixed two-decimal string suitable for DECIMAL(20,2).
     * Indonesian input such as 1.250,50 is accepted, as is 1250.50.
     */
    public static function normalize(mixed $amount): string
    {
        if (! is_scalar($amount)) {
            throw new InvalidArgumentException('Nominal tidak valid.');
        }

        $value = trim((string) $amount);
        if ($value === '' || str_starts_with($value, '-')) {
            throw new InvalidArgumentException('Nominal harus bernilai positif.');
        }

        $value = preg_replace('/\s+/', '', $value);
        if (! preg_match('/^[0-9.,]+$/', $value)) {
            throw new InvalidArgumentException('Format nominal tidak valid.');
        }

        if (str_contains($value, ',')) {
            if (substr_count($value, ',') !== 1) {
                throw new InvalidArgumentException('Gunakan hanya satu pemisah desimal.');
            }

            [$whole, $fraction] = explode(',', $value, 2);
            if (! preg_match('/^\d{1,3}(?:\.\d{3})*$|^\d+$/', $whole) || ! preg_match('/^\d{1,2}$/', $fraction)) {
                throw new InvalidArgumentException('Nominal harus memiliki paling banyak 2 digit di belakang koma.');
            }

            $whole = str_replace('.', '', $whole);
        } elseif (str_contains($value, '.')) {
            $parts = explode('.', $value);
            if (count($parts) === 2 && preg_match('/^\d+$/', $parts[0]) && preg_match('/^\d{1,2}$/', $parts[1])) {
                [$whole, $fraction] = $parts;
            } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value)) {
                $whole = str_replace('.', '', $value);
                $fraction = '';
            } else {
                throw new InvalidArgumentException('Nominal harus memiliki paling banyak 2 digit di belakang koma.');
            }
        } else {
            $whole = $value;
            $fraction = '';
        }

        $whole = ltrim($whole, '0') ?: '0';
        $fraction = str_pad($fraction, 2, '0');

        return $whole.'.'.$fraction;
    }

    public static function formatIndonesian(string $amount): string
    {
        [$whole, $fraction] = explode('.', self::normalize($amount));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction;
    }
}