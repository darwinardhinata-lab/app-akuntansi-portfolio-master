<?php
/**
 * Validate that all __('erp.XXX') keys used in views exist in all 3 lang files.
 */

$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\resources\\views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$keys = [];
foreach ($views as $v) {
    $content = file_get_contents($v);
    // Match __('erp.xxx') and __("erp.xxx")
    preg_match_all("/__\(\s*['\"]erp\.([a-zA-Z0-9_]+)['\"]/", $content, $m);
    foreach ($m[1] as $k) {
        $keys[$k] = true;
    }
}

$keys = array_keys($keys);
sort($keys);
echo "Total unique keys used in views: " . count($keys) . "\n";
echo "Total view files scanned: " . count($views) . "\n\n";

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';
$en = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

echo "Keys in lang/id/erp.php: " . count($id) . "\n";
echo "Keys in lang/en/erp.php: " . count($en) . "\n";
echo "Keys in lang/zh_CN/erp.php: " . count($zh) . "\n\n";

$missing_id = array_diff($keys, array_keys($id));
$missing_en = array_diff($keys, array_keys($en));
$missing_zh = array_diff($keys, array_keys($zh));

echo "=== Missing keys ===\n";
echo "Missing in id: " . count($missing_id) . "\n";
echo "Missing in en: " . count($missing_en) . "\n";
echo "Missing in zh_CN: " . count($missing_zh) . "\n\n";

if (count($missing_id) > 0) {
    echo "ID missing keys:\n";
    foreach ($missing_id as $k) echo "  - erp.$k\n";
}
if (count($missing_en) > 0) {
    echo "EN missing keys:\n";
    foreach ($missing_en as $k) echo "  - erp.$k\n";
}
if (count($missing_zh) > 0) {
    echo "ZH missing keys:\n";
    foreach ($missing_zh as $k) echo "  - erp.$k\n";
}

if (count($missing_id) === 0 && count($missing_en) === 0 && count($missing_zh) === 0) {
    echo "\n✅ ALL KEYS RESOLVED — 0 missing in all 3 languages\n";
} else {
    echo "\n❌ FAIL — some keys are missing\n";
}
