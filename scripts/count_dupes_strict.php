<?php
/**
 * Count duplicates using the same regex as check_dupes.php
 */

foreach (['lang/id/erp.php', 'lang/en/erp.php', 'lang/zh_CN/erp.php'] as $f) {
    $content = file_get_contents($f);
    preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content, $m);
    $counts = array_count_values($m[1]);
    $dupes = array_filter($counts, fn($c) => $c > 1);
    echo "$f: " . count($dupes) . " duplicate keys\n";
}
