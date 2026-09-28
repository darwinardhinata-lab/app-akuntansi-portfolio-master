<?php

return [
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
