<?php
$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('resources/views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$uniqueValues = [];
foreach ($views as $view) {
    $content = file_get_contents($view);
    $lines = explode("\n", $content);
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, '__(') !== false) continue;
        if (preg_match('/(placeholder|title|alt|aria-label)\s*=\s*"([^"]+)"/', $line, $matches)) {
            $attr = $matches[1];
            $val = $matches[2];
            if (empty($val) || is_numeric($val) || strpos($val, '{{') !== false || strpos($val, '<?') !== false || strpos($val, '__(') !== false) continue;
            if (preg_match('/[A-Z]/', $val) && strlen($val) > 2 && !preg_match('/^[A-Z_]+$/', $val)) {
                $uniqueValues[$attr][$val][] = $view . ':' . ($lineNum + 1);
            }
        }
    }
}

foreach ($uniqueValues as $attr => $items) {
    echo "\n=== Attr: $attr (" . count($items) . " unique values) ===\n";
    foreach ($items as $val => $locs) {
        echo "  \"$val\" (" . count($locs) . " occurrences)\n";
    }
}
