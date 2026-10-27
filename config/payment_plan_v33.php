<?php

return [
    'ap_allocations' => env('PAYMENT_PLAN_V33_AP_ALLOCATIONS', false),
    'erp_bank_balance' => env('PAYMENT_PLAN_V33_ERP_BANK_BALANCE', false),
    'bank_workflow' => env('PAYMENT_PLAN_V33_BANK_WORKFLOW', false),
    'enabled' => env('PAYMENT_PLAN_V33_ENABLED', false),
    'posting' => env('PAYMENT_PLAN_V33_POSTING', false),
    'reservations' => env('PAYMENT_PLAN_V33_RESERVATIONS', false),
    // Explicit company-scoped Platform roles, never a legacy title or browser header.
    'role_codes' => [
        'PPL_ADMIN' => 'admin', 'PPL_REQUESTER' => 'requester',
        'PPL_DIVISION_MANAGER' => 'division_manager', 'PPL_FINANCE_STAFF' => 'finance_staff',
        'PPL_FINANCE_MANAGER' => 'finance_manager', 'PPL_DIRECTOR' => 'director',
        'PPL_MAKER' => 'maker', 'PPL_GENERAL_MANAGER' => 'general_manager', 'PPL_ACCOUNTANT' => 'accountant',
    ],
    // Original mandate default only; operational changes require effective-dated approval.
    'payment_accounts' => ['idr3' => 'IDR', 'usd1' => 'USD'],
];