<?php

// FIX: multicurrency — katalog Inggris lengkap untuk label dan kesalahan modul.
return [
    'account_currency' => 'Account currency', 'account_currency_base' => 'Base currency (empty)',
    'account_currency_used' => 'Currency cannot be changed or cleared after journal entries exist.',
    'account_currency_invalid' => 'Select an active currency other than the company base currency.',
    'account_currency_control' => 'Foreign currency is not allowed for control accounts.',
    'account_currency_cash_only' => 'Foreign currency is only allowed for the Cash & Bank category.',
    // FIX: multicurrency — label formulir jurnal dan saldo awal valas.
    'override_reason' => 'Exchange rate override reason',
    'equivalent' => 'Base currency equivalent (preview)',
    'title' => 'Currency Master', 'rates' => 'Exchange Rates', 'code' => 'Code',
    'name' => 'Name', 'symbol' => 'Symbol', 'decimals' => 'Decimals', 'show' => 'Show',
    'active' => 'Active', 'base' => 'Base', 'save' => 'Save', 'delete' => 'Delete',
    'date' => 'Start date', 'until' => 'Valid until', 'rate' => 'Rate per 1 foreign currency unit',
    'type' => 'Type', 'source' => 'Source', 'created' => 'Created by / date',
    'updated' => 'Modified by / time', 'already_used' => 'Already used',
    'from' => 'From date', 'to' => 'To date', 'filter' => 'Filter',
    'import' => 'Import CSV', 'add' => 'Add rate', 'manual' => 'Manual',
    'spot' => 'Spot', 'tax' => 'Tax', 'average' => 'Average',
    'quoted_unit' => 'Quoted units', 'quoted_rate' => 'Original quoted rate',
    'quoted_help' => 'The server calculates the per-unit rate on save: original quote ÷ quoted units.',
    'kmk_help' => 'Enter the original KMK quote, quoted units and validity period. For JPY per 100 yen, enter 100 units; the server normalizes the rate.',
    'bi_help' => 'Enter BI rates per 1 foreign currency unit. Divide JPY quotes per 100 yen by 100. Finance must approve the buying/selling/midpoint policy; automatic synchronization is not available yet.',
    'csv_help' => 'CSV: currency_code,rate_date,rate,type; optional source,valid_until. TAX requires valid_until. Use decimal points without thousands separators. Maximum 1,000 rows.',
    'saved' => 'Currency/rate data saved.', 'missing' => 'No applicable exchange rate is available.',
    'invalid' => 'Invalid currency, rate, or source/type combination.',
    'duplicate' => 'Duplicate rate or overlapping tax rate validity period.',
    'used' => 'A used rate cannot be modified or deleted.',
    'range' => 'Dates must be ordered and span no more than three calendar months.',
    'csv_invalid' => 'Invalid CSV structure or content.', 'empty' => 'No data yet.',
];