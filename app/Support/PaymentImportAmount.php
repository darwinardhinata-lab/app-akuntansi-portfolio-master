<?php

namespace App\Support;

use InvalidArgumentException;

class PaymentImportAmount
{
    public static function normalize(string $value, string $format, bool $allowZero = false, int $wholeDigits = 13): string
    {
        $value = trim($value);
        if ($format === 'en') {
            if (!preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$/D', $value)) {
                throw new InvalidArgumentException(__('erp.audit_payment_import_amount'));
            }
            $value = str_replace(',', '', $value);
        } elseif ($format !== 'id' || !preg_match('/^[0-9.,]+$/D', $value)) {
            throw new InvalidArgumentException(__('erp.audit_payment_import_amount'));
        }
        try {
            $normalized = JournalAmount::normalize($value);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(__('erp.audit_payment_import_amount'), 0, $e);
        }
        [$whole] = explode('.', $normalized);
        if (strlen($whole) > $wholeDigits || (!$allowZero && $normalized === '0.00')) {
            throw new InvalidArgumentException(__('erp.audit_payment_import_amount'));
        }
        return $normalized;
    }
}