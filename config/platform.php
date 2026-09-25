<?php

return [
    // Enable only after the two A2 migrations and the read-only readiness check.
    'order_company_scope_enabled' => env('PLATFORM_ORDER_COMPANY_SCOPE_ENABLED', false),
    // Switch off at MGI cutover to prevent old dashboard data from being re-imported.
    'legacy_sync_enabled' => env('PLATFORM_LEGACY_SYNC_ENABLED', true),
];
