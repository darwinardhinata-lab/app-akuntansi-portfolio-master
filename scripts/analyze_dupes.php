<?php
$locales = ['id', 'en', 'zh_CN'];
foreach ($locales as $loc) {
    $path = "lang/{$loc}/erp.php";
    if (!file_exists($path)) continue;
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    $keyLines = [];
    foreach ($lines as $i => $line) {
        if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>\s*/", $line, $m)) {
            $keyLines[$m[1]][] = $i + 1;
        }
    }
    $dupes = array_filter($keyLines, fn($v) => count($v) > 1);
    echo "=== {$loc} ===\n";
    echo "Total unique keys: " . count($keyLines) . "\n";
    echo "Duplicate keys count: " . count($dupes) . "\n";
    foreach ($dupes as $key => $lineNums) {
        echo "  '{$key}' on lines: " . implode(', ', $lineNums) . "\n";
    }
    echo "\n";
}
