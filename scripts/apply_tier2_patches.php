<?php
/**
 * Apply Tier 2 Patches
 * 
 * Apply patches yang sudah di-review ke blade files.
 * Hanya apply patches dengan confidence 'high' secara default.
 * 
 * Cara pakai:
 * 1. Review storage/i18n/tier2_patches.json
 * 2. Edit/hapus patches yang tidak diinginkan
 * 3. Jalankan: php scripts/apply_tier2_patches.php
 * 
 * Options:
 * --include-medium  : Include medium confidence patches
 * --include-low     : Include low confidence patches (not recommended)
 * --dry-run         : Preview tanpa apply
 */

$patchesFile = __DIR__ . '/../storage/i18n/tier2_patches.json';

if (!file_exists($patchesFile)) {
    die("❌ File tidak ditemukan: {$patchesFile}\n   Jalankan: php scripts/generate_tier2_patches.php\n");
}

$patches = json_decode(file_get_contents($patchesFile), true);

// Parse options
$includeMedium = in_array('--include-medium', $argv ?? []);
$includeLow = in_array('--include-low', $argv ?? []);
$dryRun = in_array('--dry-run', $argv ?? []);

$stats = [
    'applied' => 0,
    'skipped' => 0,
    'files_updated' => [],
];

$fileContents = [];

foreach ($patches as $patch) {
    $confidence = $patch['confidence'] ?? 'medium';
    
    // Skip berdasarkan confidence
    if ($confidence === 'medium' && !$includeMedium) {
        $stats['skipped']++;
        continue;
    }
    if ($confidence === 'low' && !$includeLow) {
        $stats['skipped']++;
        continue;
    }
    
    $file = __DIR__ . '/../' . $patch['file'];
    
    if (!file_exists($file)) {
        $stats['skipped']++;
        continue;
    }
    
    // Load file content (cache untuk multiple patches di file yang sama)
    if (!isset($fileContents[$file])) {
        $fileContents[$file] = file_get_contents($file);
    }
    
    $content = &$fileContents[$file];
    $originalContent = $content;
    
    // Apply replacement
    $escaped = preg_quote($patch['text'], '/');
    $content = preg_replace(
        '/>' . $escaped . '</',
        '>{{ __(\'erp.' . $patch['key'] . '\') }}<',
        $content, -1, $count
    );
    
    if ($count > 0 && $content !== $originalContent) {
        $stats['applied']++;
        if (!in_array($patch['file'], $stats['files_updated'])) {
            $stats['files_updated'][] = $patch['file'];
        }
    }
}

// Write changes
if (!$dryRun) {
    foreach ($fileContents as $file => $content) {
        file_put_contents($file, $content);
    }
}

$stats['files_updated'] = count($stats['files_updated']);

echo "=== Apply Tier 2 Patches Results ===\n\n";
echo "Mode: " . ($dryRun ? "DRY RUN" : "APPLY") . "\n";
echo "Patches applied: {$stats['applied']}\n";
echo "Patches skipped: {$stats['skipped']}\n";
echo "Files updated: {$stats['files_updated']}\n";

if ($dryRun) {
    echo "\n✅ Dry run complete - no changes were made\n";
} else {
    echo "\n✅ Tier 2 patches applied successfully!\n";
}
