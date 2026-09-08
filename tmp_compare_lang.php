<?php
// Compare en/erp.php with zh_CN/erp.php to find missing keys
$en = include __DIR__ . '/lang/en/erp.php';
$zh = include __DIR__ . '/lang/zh_CN/erp.php';

function flatten($arr, $prefix = '') {
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? $k : $prefix . '.' . $k;
        if (is_array($v)) {
            $out += flatten($v, $key);
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

$enFlat = flatten($en);
$zhFlat = flatten($zh);

echo "EN flat keys: " . count($enFlat) . PHP_EOL;
echo "ZH flat keys: " . count($zhFlat) . PHP_EOL;

$missing = [];
foreach ($enFlat as $k => $v) {
    if (!array_key_exists($k, $zhFlat)) {
        $missing[$k] = $v;
    }
}
echo "Missing in ZH: " . count($missing) . PHP_EOL;
file_put_contents(__DIR__ . '/tmp_missing_zh.txt', '');
foreach ($missing as $k => $v) {
    $line = $k . ' = ' . $v . PHP_EOL;
    file_put_contents(__DIR__ . '/tmp_missing_zh.txt', $line, FILE_APPEND);
}
echo "Written to tmp_missing_zh.txt" . PHP_EOL;
