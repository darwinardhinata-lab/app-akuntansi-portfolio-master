<?php

namespace App\Support;

class NumberParser
{
    /**
     * Parse decimal number from various formats.
     * Supports:
     * - Indonesian format: 1.000.000,50 (dot for thousands, comma for decimal)
     * - US/Euro format: 1,000,000.50 (comma for thousands, dot for decimal)
     * - Simple format: 1000.50 or 1000,50
     *
     * @param string|null $val
     * @return float
     */
    public static function parseDecimal(?string $val): float
    {
        $val = trim($val ?? '0');
        
        if ($val === '' || $val === '-') {
            return 0.0;
        }
        
        // Remove any non-numeric characters except dots, commas, and minus
        $cleaned = preg_replace('/[^0-9\.,\-]/', '', $val);
        
        if ($cleaned === '' || $cleaned === '-') {
            return 0.0;
        }
        
        // Detect format by position and count of separators
        // Indonesian format: dots as thousands, comma as decimal (e.g., "1.000.000,50")
        // US/Euro format: commas as thousands, dot as decimal (e.g., "1,000,000.50")
        
        $dotCount = substr_count($cleaned, '.');
        $commaCount = substr_count($cleaned, ',');
        
        if ($commaCount >= 1 && preg_match('/,\d{1,2}$/', $cleaned)) {
            // Indonesian format: 1.000.000,50 (comma as decimal, dots as thousands)
            // Remove all dots (thousands), convert comma to dot (decimal)
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif ($dotCount >= 1 && preg_match('/\.\d{1,2}$/', $cleaned) && $commaCount > 0) {
            // US/Euro format: 1,000,000.50 (dot as decimal, commas as thousands)
            // Remove all commas (thousands)
            $cleaned = str_replace(',', '', $cleaned);
        } elseif ($dotCount > 1) {
            // Indonesian format without decimals: 1.000.000 (multiple dots = thousands separators)
            $cleaned = str_replace('.', '', $cleaned);
        } elseif ($commaCount > 1) {
            // US/Euro format without decimals: 1,000,000 (multiple commas = thousands separators)
            $cleaned = str_replace(',', '', $cleaned);
        } elseif ($dotCount === 1 && preg_match('/\.\d{3}$/', $cleaned)) {
            // Ambiguous: single dot followed by 3 digits (e.g., "1.000")
            // In Indonesian context without comma decimal, this is thousands separator
            // But in US context, this is decimal. Since we're in Indonesian ERP, treat as thousands
            $cleaned = str_replace('.', '', $cleaned);
        } else {
            // Simple format: remove all commas (no thousands separators)
            $cleaned = str_replace(',', '', $cleaned);
        }
        
        return (float) $cleaned;
    }

    /**
     * Format number to Indonesian currency format (Rp 1.000.000,00)
     *
     * @param float|int $amount
     * @return string
     */
    public static function formatCurrency($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 2, ',', '.');
    }
}