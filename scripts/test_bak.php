<?php
$file = 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php.bak';
$content = file_get_contents($file);

preg_match_all("/\x27([a-zA-Z0-9_]+)\x27\s*=>\s*\x27/", $content, $m);
echo "bak file: " . count($m[1]) . " keys\n";
$counts = array_count_values($m[1]);
$dupes = array_filter($counts, fn($c) => $c > 1);
echo "Duplicate groups: " . count($dupes) . "\n";
if (count($dupes) > 0) {
    foreach (array_slice($dupes, 0, 5, true) as $k => $c) {
        echo "  '$k' => $c times\n";
    }
}

echo "\n--- Current file ---\n";
$content2 = file_get_contents('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php');
preg_match_all("/\x27([a-zA-Z0-9_]+)\x27\s*=>\s*\x27/", $content2, $m2);
echo "current file: " . count($m2[1]) . " keys\n";
$counts2 = array_count_values($m2[1]);
$dupes2 = array_filter($counts2, fn($c) => $c > 1);
echo "Duplicate groups: " . count($dupes2) . "\n";

echo "\n--- File sizes ---\n";
echo "bak: " . strlen($content) . " bytes\n";
echo "current: " . strlen($content2) . " bytes\n";
echo "identical: " . ($content === $content2 ? 'YES' : 'NO') . "\n";
