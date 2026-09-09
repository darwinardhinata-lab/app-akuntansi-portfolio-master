<?php
/**
 * Generate Tier 2 Patches
 * 
 * Generate patch file untuk Tier 2 (HTML text nodes sederhana)
 * yang bisa di-review sebelum apply.
 * 
 * Cara pakai:
 * 1. Jalankan: php scripts/extract_and_categorize.php
 * 2. Translate: php scripts/batch_translate.php --tier=tier2_simple
 * 3. Jalankan: php scripts/generate_tier2_patches.php
 * 4. Review file: storage/i18n/tier2_patches.json
 * 5. Apply manual atau gunakan apply_tier2_patches.php
 */

$tier2File = __DIR__ . '/../storage/i18n/hardcoded_tier2_simple.json';
$translationsFile = __DIR__ . '/../storage/i18n/translated_tier2.json';

if (!file_exists($tier2File)) {
    die("❌ File tidak ditemukan: {$tier2File}\n   Jalankan: php scripts/extract_and_categorize.php\n");
}

if (!file_exists($translationsFile)) {
    die("❌ File tidak ditemukan: {$translationsFile}\n   Jalankan: php scripts/batch_translate.php --tier=tier2_simple\n");
}

$tier2 = json_decode(file_get_contents($tier2File), true);
$translations = json_decode(file_get_contents($translationsFile), true);

$patches = [];
$stats = [
    'total_patches' => 0,
    'high_confidence' => 0,
    'medium_confidence' => 0,
    'low_confidence' => 0,
];

foreach ($tier2 as $text => $occurrences) {
    $key = $translations[$text]['key'] ?? null;
    if (!$key) {
        continue;
    }
    
    foreach ($occurrences as $occ) {
        $context = $occ['context'];
        $escaped = preg_quote($text, '/');
        
        // Generate suggested replacement
        $suggested = preg_replace(
            '/>' . $escaped . '</',
            '>{{ __(\'erp.' . $key . '\') }}<',
            $context
        );
        
        // Determine confidence level
        $confidence = 'high';
        
        // Medium confidence: multi-word text or contains special chars
        if (str_word_count($text) > 5 || preg_match('/[^\w\s]/', $text)) {
            $confidence = 'medium';
        }
        
        // Low confidence: very short or ambiguous
        if (strlen($text) < 8 || strpos($text, ' ') === false) {
            $confidence = 'low';
        }
        
        $patches[] = [
            'file' => $occ['file'],
            'line' => $occ['line'],
            'original' => $context,
            'suggested' => $suggested,
            'text' => $text,
            'key' => 'erp.' . $key,
            'confidence' => $confidence,
        ];
        
        $stats['total_patches']++;
        $stats[$confidence . '_confidence']++;
    }
}

// Export ke format yang bisa di-review
$outputDir = __DIR__ . '/../storage/i18n';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents(
    "{$outputDir}/tier2_patches.json",
    json_encode($patches, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "=== Generate Tier 2 Patches Results ===\n\n";
echo "Total patches generated: {$stats['total_patches']}\n";
echo "High confidence: {$stats['high_confidence']}\n";
echo "Medium confidence: {$stats['medium_confidence']}\n";
echo "Low confidence: {$stats['low_confidence']}\n";
echo "\nReview file: storage/i18n/tier2_patches.json\n";
echo "\nNext steps:\n";
echo "1. Review patches manually\n";
echo "2. Edit patches if needed\n";
echo "3. Run: php scripts/apply_tier2_patches.php\n";
