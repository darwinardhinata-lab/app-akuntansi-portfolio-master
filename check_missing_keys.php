<?php

// Compare language files and find missing keys with their English values
$en = include 'lang/en/erp.php';
$zh = include 'lang/zh_CN/erp.php';

$missing = array_diff_key($en, $zh);
echo "Missing from zh_CN: " . count($missing) . "\n";

// Output missing keys with English values in a simple format
$lines = [];
foreach ($missing as $key => $value) {
    $lines[] = $key . "\t" . $value;
}
file_put_contents('tmp_missing_clean.txt', implode("\n", $lines));
echo "Wrote " . count($lines) . " missing keys to tmp_missing_clean.txt\n";

echo "\nFirst 30 missing keys:\n";
$count = 0;
foreach ($missing as $key => $value) {
    echo "  $key => $value\n";
    $count++;
    if ($count >= 30) break;
}
