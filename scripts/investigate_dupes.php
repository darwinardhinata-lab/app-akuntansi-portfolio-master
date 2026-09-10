<?php
/**
 * Investigate duplicate key formats in en/erp.php.
 */

$file = 'D:\\xampp\\htdocs\\app-akuntansi-portfolio-master\\lang\\en\\erp.php';
$lines = file($file);

// Find all key entries with different regex patterns
$key_lines = [];
foreach ($lines as $line_num => $line) {
    // Pattern 1: simple 'key' => 'value',
    if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>\s*'/", $line, $m)) {
        $key_lines[$m[1]][] = $line_num;
    }
}

// Find duplicates
$dupes = array_filter($key_lines, fn($l) => count($l) > 1);
echo "Duplicates found with simple pattern: " . count($dupes) . "\n\n";

// Show first 5 duplicates with their content
$shown = 0;
foreach ($dupes as $key => $line_nums) {
    if ($shown >= 5) break;
    echo "=== Key: '$key' (appears " . count($line_nums) . " times) ===\n";
    foreach ($line_nums as $ln) {
        echo "  Line " . ($ln + 1) . ": " . trim($lines[$ln]) . "\n";
    }
    echo "\n";
    $shown++;
}

// Check for keys that might use different patterns
echo "\n=== Checking for alternative patterns ===\n";
$alt_count = 0;
foreach ($lines as $line_num => $line) {
    // Look for lines that might be key => value but not matched
    if (preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>/", $line, $m)) {
        if (!preg_match("/^\s*'([a-zA-Z0-9_]+)'\s*=>\s*'/", $line)) {
            $alt_count++;
            if ($alt_count <= 3) {
                echo "  Line " . ($ln + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}
echo "Alternative patterns found: $alt_count\n";
