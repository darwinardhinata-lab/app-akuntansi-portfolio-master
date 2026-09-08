<?php
$en = include 'lang/en/erp.php';
$zh = include 'lang/zh_CN/erp.php';
$id = include 'lang/id/erp.php';

echo "EN keys: " . count($en) . PHP_EOL;
echo "ID keys: " . count($id) . PHP_EOL;
echo "ZH keys: " . count($zh) . PHP_EOL;

$missing = array_diff(array_keys($en), array_keys($zh));
echo "\nMissing keys in zh_CN (" . count($missing) . "):\n";
foreach($missing as $k) {
    echo "  '" . $k . "' => '" . str_replace("'", "\\'", $en[$k]) . "',\n";
}
