<?php
$views = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('D:\xampp\htdocs\app-akuntansi-portfolio-master\resources\views')
);
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $views[] = $file->getPathname();
    }
}

$results = [];
foreach ($views as $view) {
    $content = file_get_contents($view);
    $lines = explode("\n", $content);
    $view_results = array();
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, '__(') !== false) continue;
        if (preg_match('/(placeholder|title|alt|aria-label)\s*=\s*"([^"]+)"/', $line, $matches)) {
            $attr = $matches[1];
            $val = $matches[2];
            if (empty($val) || is_numeric($val) || strpos($val, '{{') !== false || strpos($val, '<?') !== false || strpos($val, '__(') !== false) continue;
            if (preg_match('/[A-Z]/', $val) && strlen($val) > 2 && !preg_match('/^[A-Z_]+$/', $val)) {
                $view_results[] = array('line' => $lineNum + 1, 'attr' => $attr, 'value' => $val);
            }
        }
    }
    if (count($view_results) > 0) $results[$view] = $view_results;
}

echo "Files with issues: " . count($results) . "\n";
$total = 0;
foreach ($results as $view => $items) {
    $short = basename($view);
    echo "\n--- $short (" . count($items) . ") ---";
    foreach ($items as $item) {
        echo "\n  L" . $item['line'] . ": " . $item['attr'] . "=\"" . $item['value'] . "\"";
        $total++;
    }
}
echo "\n\nTotal: $total\n";
