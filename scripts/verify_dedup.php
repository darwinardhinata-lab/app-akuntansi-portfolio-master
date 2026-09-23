<?php
/**
 * Verify en/erp.php deduplication and identify remaining issues.
 */

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';
$en = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

echo "Key counts:\n";
echo "  id: " . count($id) . "\n";
echo "  en: " . count($en) . "\n";
echo "  zh: " . count($zh) . "\n\n";

// Check id/erp.php for the new duplicate
echo "=== id/erp.php duplicate: select_sales_invoice ===\n";
$content = file_get_contents('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (str_contains($line, "'select_sales_invoice'")) {
        echo "  Line " . ($i + 1) . ": " . trim($line) . "\n";
    }
}

// Check zh_CN remaining duplicates
echo "\n=== zh_CN/erp.php remaining duplicates (46) ===\n";
$content_zh = file_get_contents('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php');
preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content_zh, $m);
$counts = array_count_values($m[1]);
$dupes = array_filter($counts, fn($c) => $c > 1);
echo "Total duplicate keys: " . count($dupes) . "\n";

// Check if any duplicate keys are used in views
$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\resources\\views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$used_dupes = [];
foreach (array_keys($dupes) as $k) {
    foreach ($views as $v) {
        if (str_contains(file_get_contents($v), "erp.$k")) {
            $used_dupes[] = $k;
            break;
        }
    }
}

echo "Duplicate keys used in views: " . count($used_dupes) . "\n";
if (count($used_dupes) > 0) {
    echo "  Keys: " . implode(', ', array_slice($used_dupes, 0, 20)) . "\n";
}
