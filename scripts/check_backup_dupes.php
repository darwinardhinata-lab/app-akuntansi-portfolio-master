<?php
/**
 * Check duplicates in backup file
 */

$content = file_get_contents('D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php.bak');
preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content, $m);
$counts = array_count_values($m[1]);
$dupes = array_filter($counts, fn($c) => $c > 1);
echo "Backup en/erp.php: " . count($dupes) . " duplicate keys\n";
echo "Total key occurrences: " . count($m[1]) . "\n";
echo "Unique keys: " . count($counts) . "\n";
