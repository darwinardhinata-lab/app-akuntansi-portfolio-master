<?php
/**
 * Smoke test: verify zh_CN translations resolve correctly and no raw key leakage.
 * Run from project root: php scripts/smoke_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\App;

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';
$en = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

$errors = 0;
$tests = 0;

echo "=== SMOKE TEST: zh_CN Translation Verification ===\n\n";

// Test 1: Key zh_CN values are actually Chinese (CJK characters)
echo "Test 1: zh_CN values contain CJK characters\n";
$sample_keys = ['dashboard', 'save_btn', 'search_btn', 'accounting', 'general_journal', 'description', 'date', 'add_new', 'edit', 'delete'];
$chinese_count = 0;
foreach ($sample_keys as $k) {
    $tests++;
    if (isset($zh[$k])) {
        // Check if value contains CJK characters
        if (preg_match('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $zh[$k])) {
            $chinese_count++;
        } else {
            echo "  ⚠ Key '$k' value '{$zh[$k]}' may not be Chinese\n";
        }
    } else {
        echo "  ❌ Key '$k' missing in zh_CN\n";
        $errors++;
    }
}
echo "  Result: $chinese_count/" . count($sample_keys) . " keys have Chinese values\n\n";

// Test 2: Previously corrupted 'description' key is fixed
echo "Test 2: Corrupted 'description' key fixed\n";
$tests++;
if (isset($zh['description']) && $zh['description'] !== 'description }}">') {
    echo "  ✅ 'description' key value: '{$zh['description']}'\n";
} else {
    echo "  ❌ 'description' key still corrupted or missing\n";
    $errors++;
}
echo "\n";

// Test 3: AR Subledger specific keys exist
echo "Test 3: AR Subledger specific keys exist\n";
$ar_keys = ['ar_sub_date', 'ar_sub_description', 'ar_sub_no_data'];
foreach ($ar_keys as $k) {
    $tests++;
    if (isset($zh[$k]) && isset($id[$k]) && isset($en[$k])) {
        echo "  ✅ '$k': ID='{$id[$k]}' | EN='{$en[$k]}' | ZH='{$zh[$k]}'\n";
    } else {
        echo "  ❌ '$k' missing in one or more languages\n";
        $errors++;
    }
}
echo "\n";

// Test 4: Common keys resolve in all 3 languages
echo "Test 4: Common UI keys resolve in all 3 languages\n";
$common_keys = ['main_menu', 'dashboard', 'save', 'edit', 'delete', 'cancel', 'search', 'action', 'status', 'print', 'export', 'detail', 'add_new', 'close'];
$all_resolved = 0;
foreach ($common_keys as $k) {
    $tests++;
    if (isset($id[$k]) && isset($en[$k]) && isset($zh[$k])) {
        $all_resolved++;
    } else {
        echo "  ❌ '$k' missing: " . (isset($id[$k]) ? 'ID✓' : 'ID✗') . (isset($en[$k]) ? 'EN✓' : 'EN✗') . (isset($zh[$k]) ? 'ZH✓' : 'ZH✗') . "\n";
        $errors++;
    }
}
echo "  Result: $all_resolved/" . count($common_keys) . " keys resolved in all 3 languages\n\n";

// Test 5: Verify no Blade syntax corruption in zh_CN values
echo "Test 5: No Blade syntax corruption in zh_CN values\n";
$corrupt_count = 0;
foreach ($zh as $k => $v) {
    if (is_string($v) && (str_contains($v, '{{') || str_contains($v, '}}') || str_contains($v, '<?php'))) {
        $corrupt_count++;
        if ($corrupt_count <= 5) {
            echo "  ⚠ Potentially corrupt key '$k': '$v'\n";
        }
    }
}
$tests++;
if ($corrupt_count === 0) {
    echo "  ✅ No corruption detected in " . count($zh) . " zh_CN values\n";
} else {
    echo "  ❌ $corrupt_count potentially corrupt values found\n";
    $errors++;
}
echo "\n";

// Test 6: Translation function works for zh_CN
echo "Test 6: __('erp.xxx') resolution via Laravel\n";
App::setLocale('zh_CN');
$tests++;
$resolved = __('erp.dashboard');
if ($resolved !== 'erp.dashboard' && !empty($resolved)) {
    echo "  ✅ __('erp.dashboard') resolves to: '$resolved'\n";
} else {
    echo "  ❌ __('erp.dashboard') not resolving (returned: '$resolved')\n";
    $errors++;
}

App::setLocale('id');
$resolved_id = __('erp.dashboard');
echo "  ℹ __('erp.dashboard') in id: '$resolved_id'\n";

App::setLocale('en');
$resolved_en = __('erp.dashboard');
echo "  ℹ __('erp.dashboard') in en: '$resolved_en'\n";

echo "\n=== SUMMARY ===\n";
echo "Tests: $tests, Errors: $errors\n";
echo $errors === 0 ? "✅ ALL TESTS PASSED\n" : "❌ SOME TESTS FAILED\n";
