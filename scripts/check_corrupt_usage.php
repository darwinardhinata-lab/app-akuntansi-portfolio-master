<?php
/**
 * Check if corrupt keys are actually used in views.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

// Find all corrupt keys (containing Blade syntax remnants)
$corrupt_keys = [];
foreach ($zh as $k => $v) {
    if (is_string($v) && (str_contains($v, '{{') || str_contains($v, '}}') || str_contains($v, '<?php') || str_contains($v, '"])'))) {
        $corrupt_keys[] = $k;
    }
}

echo "Corrupt keys found: " . count($corrupt_keys) . "\n\n";

// Scan views for usage of these keys
$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\resources\\views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$used_in_views = [];
$not_used = [];

foreach ($corrupt_keys as $k) {
    $found = false;
    foreach ($views as $v) {
        $content = file_get_contents($v);
        if (str_contains($content, "erp.$k")) {
            $used_in_views[$k] = $v;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $not_used[] = $k;
    }
}

echo "=== Corrupt keys USED in views (NEED FIX) ===\n";
echo "Count: " . count($used_in_views) . "\n";
foreach ($used_in_views as $k => $v) {
    echo "  - erp.$k (used in " . basename($v) . ")\n";
}

echo "\n=== Corrupt keys NOT used in views (safe) ===\n";
echo "Count: " . count($not_used) . "\n";
if (count($not_used) <= 20) {
    foreach ($not_used as $k) {
        echo "  - $k\n";
    }
}
