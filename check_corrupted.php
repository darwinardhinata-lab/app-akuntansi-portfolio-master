<?php
$en = include 'lang/en/erp.php';
$zh = include 'lang/zh_CN/erp.php';

$corrupted = [];
foreach ($en as $key => $enVal) {
    $zhVal = $zh[$key] ?? '';
    // Check if Chinese value contains English words (corrupted)
    if (preg_match('/[a-zA-Z]{3}/', $zhVal) && !preg_match('/[\x{4e00}-\x{9fff}]/u', $zhVal)) {
        $corrupted[$key] = ['en' => $enVal, 'zh' => $zhVal];
    }
    // Also check for mixed content (English words embedded in Chinese)
    elseif (preg_match('/[a-zA-Z]{4,}/', $zhVal) && preg_match('/[\x{4e00}-\x{9fff}]/u', $zhVal)) {
        $corrupted[$key] = ['en' => $enVal, 'zh' => $zhVal];
    }
}

echo "Corrupted zh_CN translations (" . count($corrupted) . "):\n";
foreach ($corrupted as $key => $vals) {
    echo "  '" . $key . "' => '" . $vals['zh'] . "' (EN: " . $vals['en'] . ")\n";
}
