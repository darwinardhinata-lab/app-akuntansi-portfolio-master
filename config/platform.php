<?php

return [
    // Switch off at MGI cutover to prevent old dashboard data from being re-imported.
    'legacy_sync_enabled' => env('PLATFORM_LEGACY_SYNC_ENABLED', true),
];
