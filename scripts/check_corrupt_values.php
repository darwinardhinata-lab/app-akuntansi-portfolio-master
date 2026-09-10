<?php
/**
 * Check corrupt key values across all 3 languages.
 */

$id = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\id\\erp.php';
$en = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$zh = include 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php';

$keys = ['account_name', 'color', 'email', 'gsm', 'name', 'phone', 'subtype', 'width'];

foreach ($keys as $k) {
    echo "\n=== $k ===\n";
    echo "  ID: " . ($id[$k] ?? 'MISSING') . "\n";
    echo "  EN: " . ($en[$k] ?? 'MISSING') . "\n";
    echo "  ZH: " . ($zh[$k] ?? 'MISSING') . "\n";
}

echo "\n=== Checking zh_CN file for duplicate entries of these keys ===\n";
$content = file_get_contents('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\zh_CN\\erp.php');
foreach ($keys as $k) {
    $count = substr_count($content, "'$k'");
    echo "  '$k' appears $count times in zh_CN\n";
}
