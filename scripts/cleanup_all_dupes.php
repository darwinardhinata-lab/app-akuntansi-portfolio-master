<?php
$files = [
    'lang/id/erp.php',
    'lang/en/erp.php',
    'lang/zh_CN/erp.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    // Track keys and their line indexes
    $keyLines = [];
    foreach ($lines as $i => $line) {
        if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>/", $line, $m)) {
            $keyLines[$m[1]][] = $i;
        }
    }

    // Identify duplicates: keep the LAST occurrence, remove earlier ones
    $toRemove = [];
    foreach ($keyLines as $key => $indices) {
        if (count($indices) > 1) {
            $earlier = array_slice($indices, 0, -1);
            foreach ($earlier as $idx) {
                $toRemove[$idx] = $key;
            }
        }
    }

    echo "File {$file}: removing " . count($toRemove) . " duplicate lines.\n";

    $newLines = [];
    foreach ($lines as $i => $line) {
        if (isset($toRemove[$i])) {
            continue;
        }
        $newLines[] = $line;
    }

    file_put_contents($file, implode("\n", $newLines));

    // Verify syntax
    $data = include $file;
    echo "  Total active keys: " . count($data) . "\n";
}
echo "Done deduping.\n";
