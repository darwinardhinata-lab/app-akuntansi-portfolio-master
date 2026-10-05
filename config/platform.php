<?php

return [
    // FIX: akses PO memerlukan ID eksplisit; semua allowlist default kosong.
    'po_view_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PO_VIEW_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'po_create_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PO_CREATE_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'po_approve_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PO_APPROVE_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    // FIX: akses PR hanya melalui ID eksplisit; seluruh allowlist default kosong.
    'pr_view_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PR_VIEW_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'pr_create_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PR_CREATE_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'pr_approve_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PR_APPROVE_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'maklun_reversal_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('MAKLUN_REVERSAL_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    'maklun_receipt_enabled' => env('PLATFORM_MAKLUN_RECEIPT_ENABLED', false),
    // Keep off until receipt/reversal lifecycle and historical issues are reviewed.
    'maklun_issue_enabled' => env('PLATFORM_MAKLUN_ISSUE_ENABLED', false),
    // Explicit FINANCE user IDs only; empty by default, no ADMIN bypass.
    'payment_correction_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('PAYMENT_CORRECTION_USER_IDS', ''))
    ), fn ($id) => ctype_digit($id) && (int) $id > 0)),
    // Recovery-only opt-in. Default blocks destructive Artisan commands on
    // operational mgi_fresh_* databases; see OperationalDatabaseSafety.
    'allow_destructive_database_commands' => env('ALLOW_DESTRUCTIVE_MGI_DATABASE_COMMANDS', false),
    'grn_enabled' => env('PLATFORM_GRN_ENABLED', false),
    // MGI V1 mappings approved for GRN only; do not replace the legacy COA mappings.
    'grn_inventory_account' => '114001', // Barang jadi (DEBET)
    'grn_payable_account' => '211001', // Utang usaha (KREDIT)
    // Enable only after the two A2 migrations and the read-only readiness check.
    'order_company_scope_enabled' => env('PLATFORM_ORDER_COMPANY_SCOPE_ENABLED', false),
    // Switch off at MGI cutover to prevent old dashboard data from being re-imported.
    // FIX: Default aman = false (fail-safe). Deploy tanpa .env yang benar tidak lagi
    // menyalakan sinkronisasi/ETL legacy yang dapat mengimpor ulang data lama.
    'legacy_sync_enabled' => env('PLATFORM_LEGACY_SYNC_ENABLED', false),
];
