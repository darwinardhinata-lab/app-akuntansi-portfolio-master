<?php
/**
 * Apply Tier 1 Safe Translations
 * 
 * Hanya apply translations untuk Tier 1 (placeholder, title, aria-label)
 * yang 100% aman untuk auto-replace.
 * 
 * Cara pakai:
 * 1. Jalankan: php scripts/extract_and_categorize.php
 * 2. Translate: php scripts/batch_translate.php --tier=tier1_safe
 * 3. Jalankan: php scripts/apply_tier1_safe.php
 */

$tier1File = __DIR__ . '/../storage/i18n/hardcoded_tier1_safe.json';
$translationsFile = __DIR__ . '/../storage/i18n/translated_tier1.json';

if (!file_exists($tier1File)) {
    die("❌ File tidak ditemukan: {$tier1File}\n   Jalankan: php scripts/extract_and_categorize.php\n");
}

if (!file_exists($translationsFile)) {
    die("❌ File tidak ditemukan: {$translationsFile}\n   Jalankan: php scripts/batch_translate.php --tier=tier1_safe\n");
}

$tier1 = json_decode(file_get_contents($tier1File), true);
$translations = json_decode(file_get_contents($translationsFile), true);

$stats = [
    'files_updated' => 0,
    'replacements' => 0,
    'skipped' => 0,
];

$filesUpdated = [];

foreach ($tier1 as $text => $occurrences) {
    $key = $translations[$text]['key'] ?? null;
    if (!$key) {
        $stats['skipped']++;
        continue;
    }
    
    foreach ($occurrences as $occ) {
        $file = __DIR__ . '/../' . $occ['file'];
        
        if (!file_exists($file)) {
            $stats['skipped']++;
            continue;
        }
        
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Replace hanya di attributes (AMAN)
        $escaped = preg_quote($text, '/');
        $newContent = preg_replace(
            '/(placeholder|title|aria-label)="(' . $escaped . ')"/',
            '$1="{{ __(\'erp.' . $key . '\') }}"',
            $content, -1, $count
        );
        
        if ($count > 0 && $newContent !== $originalContent) {
            file_put_contents($file, $newContent);
            $stats['replacements'] += $count;
            
            if (!in_array($occ['file'], $filesUpdated)) {
                $filesUpdated[] = $occ['file'];
            }
        }
    }
}

$stats['files_updated'] = count($filesUpdated);

echo "=== Apply Tier 1 Results ===\n\n";
echo "Replacements made: {$stats['replacements']}\n";
echo "Files updated: {$stats['files_updated']}\n";
echo "Skipped: {$stats['skipped']}\n";
echo "\n✅ Tier 1 translations applied successfully!\n";
echo "\nNext steps:\n";
echo "1. Test the updated files\n";
echo "2. Run: php scripts/generate_tier2_patches.php\n";
