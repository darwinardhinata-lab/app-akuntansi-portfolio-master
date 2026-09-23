<?php
/**
 * Deduplicate keys in lang/en/erp.php.
 * Keeps first occurrence of each key (PHP array semantics).
 */

$file = 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

// Use the same regex that successfully detects duplicates
preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content, $m);
$all_keys = $m[1];

echo "Total keys found: " . count($all_keys) . "\n";

$counts = array_count_values($all_keys);
$dupes = array_filter($counts, fn($c) => $c > 1);
echo "Duplicate key groups: " . count($dupes) . "\n";
echo "Total duplicate instances: " . (count($all_keys) - count($counts)) . "\n\n";

// Find line numbers for each key occurrence
$key_occurrences = [];
foreach ($lines as $i => $line) {
    if (preg_match("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $line, $m)) {
        $key = $m[1];
        if (!isset($key_occurrences[$key])) {
            $key_occurrences[$key] = [];
        }
        $key_occurrences[$key][] = $i;
    }
}

// Build set of lines to skip (all duplicate occurrences after the first)
$skip_lines = [];
foreach ($key_occurrences as $key => $line_nums) {
    if (count($line_nums) > 1) {
        for ($j = 1; $j < count($line_nums); $j++) {
            $skip_lines[$line_nums[$j]] = true;
        }
    }
}

echo "Lines with duplicate keys: " . count($skip_lines) . "\n";

// Find all key line positions
$all_key_lines = [];
foreach ($lines as $i => $line) {
    if (preg_match("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $line)) {
        $all_key_lines[] = $i;
    }
}

// For each duplicate line, skip until the next key line
$skip_ranges = [];
foreach ($skip_lines as $line => $true) {
    $next_key_line = count($lines);
    foreach ($all_key_lines as $kl) {
        if ($kl > $line) {
            $next_key_line = $kl;
            break;
        }
    }
    for ($l = $line; $l < $next_key_line; $l++) {
        $skip_ranges[$l] = true;
    }
}

echo "Total lines to remove: " . count($skip_ranges) . "\n";

$new_lines = [];
$removed = 0;
foreach ($lines as $i => $line) {
    if (!isset($skip_ranges[$i])) {
        $new_lines[] = $line;
    } else {
        $removed++;
    }
}

$new_content = implode("\n", $new_lines);

file_put_contents($file . '.tmp', $new_content);
$output = [];
$retcode = 0;
exec('php -l ' . escapeshellarg($file . '.tmp') . ' 2>&1', $output, $retcode);

if ($retcode === 0) {
    copy($file, $file . '.bak');
    file_put_contents($file, $new_content);
    unlink($file . '.tmp');
    echo "\nDedup complete! Original backed up to en/erp.php.bak\n";
    echo "Removed $removed lines\n";
    echo "New file: " . count($new_lines) . " lines (was " . count($lines) . ")\n";
} else {
    echo "\nSyntax error in deduped file, NOT applying changes:\n";
    echo implode("\n", $output) . "\n";
}
