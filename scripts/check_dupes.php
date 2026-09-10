<?php
/**
 * Check for duplicate array keys in lang files.
 */

foreach (['lang/id/erp.php', 'lang/en/erp.php', 'lang/zh_CN/erp.php'] as $f) {
    $content = file_get_contents($f);
    // Match 'key' => ' pattern
    preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content, $m);
    $counts = array_count_values($m[1]);
    $dupes = array_filter($counts, fn($c) => $c > 1);
    echo "$f: " . count($dupes) . " duplicate keys\n";
    if (count($dupes) > 0) {
        arsort($dupes);
        $shown = 0;
        foreach ($dupes as $k => $c) {
            echo "  - '$k' appears $c times\n";
            $shown++;
            if ($shown >= 20) {
                echo "  ... and " . (count($dupes) - 20) . " more\n";
                break;
            }
        }
    }
    echo "\n";
}
