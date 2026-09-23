<?php
/**
 * Clean up duplicate keys in en/erp.php.
 * Strategy: For each duplicate key, keep only the LAST occurrence (which is the active value in PHP).
 * This preserves the winning value while removing redundant earlier entries.
 *
 * Usage: php scripts/cleanup_dupes.php [--dry-run]
 */

$dry_run = in_array('--dry-run', $argv ?? []);
$file = 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';

$content = file_get_contents($file);
$lines = explode("\n", $content);

// First pass: find all key occurrences and their line indices
$key_lines = []; // key => [line_index, ...]
foreach ($lines as $i => $line) {
    if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>/", $line, $m)) {
        $key_lines[$m[1]][] = $i;
    }
}

// Determine which lines to remove (all occurrences except the last for each duplicate key)
$lines_to_remove = [];
foreach ($key_lines as $key => $line_indices) {
    if (count($line_indices) > 1) {
        // Remove all but the last occurrence
        $to_remove = array_slice($line_indices, 0, -1);
        foreach ($to_remove as $idx) {
            $lines_to_remove[$idx] = $key;
        }
    }
}

echo "Lines to remove: " . count($lines_to_remove) . "\n";
echo "Unique duplicate keys: " . count(array_unique(array_values($lines_to_remove))) . "\n";

if ($dry_run) {
    echo "\n=== DRY RUN - showing first 20 lines to remove ===\n";
    $count = 0;
    foreach ($lines_to_remove as $idx => $key) {
        if ($count >= 20) break;
        echo "  Line " . ($idx + 1) . ": '$key' => " . trim($lines[$idx]) . "\n";
        $count++;
    }
    echo "\nNo changes made (dry run).\n";
    exit(0);
}

// Second pass: rebuild the file, skipping removed lines
$new_lines = [];
$removed = 0;
foreach ($lines as $i => $line) {
    if (isset($lines_to_remove[$i])) {
        $removed++;
        continue; // Skip this line
    }
    $new_lines[] = $line;
}

// Write the cleaned file
$new_content = implode("\n", $new_lines);
file_put_contents($file, $new_content);

echo "Removed $removed duplicate lines from $file\n";

// Verify
$id = include $file;
echo "Remaining keys: " . count($id) . "\n";

// Check for remaining duplicates
$content2 = file_get_contents($file);
preg_match_all("/'([a-zA-Z0-9_]+)'\s*=>\s*'/", $content2, $m);
$counts = array_count_values($m[1]);
$dupes = array_filter($counts, fn($c) => $c > 1);
echo "Remaining duplicate keys: " . count($dupes) . "\n";
