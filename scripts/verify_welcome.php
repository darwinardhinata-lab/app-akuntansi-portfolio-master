<?php
/**
 * Verify welcome page translations resolve correctly.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\App;

$keys = [
    'welcome_dashboard', 'welcome_login', 'welcome_register',
    'welcome_lets_get_started', 'welcome_rich_ecosystem',
    'welcome_read_the', 'welcome_documentation',
    'welcome_laracasts_title', 'welcome_laracasts_desc',
    'welcome_deploy_now',
];

foreach (['id', 'en', 'zh_CN'] as $locale) {
    App::setLocale($locale);
    echo "=== $locale ===\n";
    foreach ($keys as $k) {
        $val = __("erp.$k");
        $status = ($val !== "erp.$k") ? '✅' : '❌';
        echo "  $status $k => $val\n";
    }
    echo "\n";
}
