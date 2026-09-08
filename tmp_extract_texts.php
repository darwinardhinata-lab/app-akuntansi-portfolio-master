<?php
// Extract unique texts only from scan output, filter false positives
$lines = file(__DIR__ . '/tmp_hardcoded_current.txt', FILE_IGNORE_NEW_LINES);
$texts = [];
$inList = false;
foreach ($lines as $line) {
    if (strpos($line, '===START===') !== false) { $inList = true; continue; }
    if (strpos($line, '===END===') !== false) { $inList = false; continue; }
    if (!$inList) continue;
    $pos = strrpos($line, ' || ');
    $text = $pos !== false ? substr($line, 0, $pos) : $line;
    $text = trim($text);
    if ($text === '') continue;
    // Filter obvious false positives: blade/code fragments
    if (preg_match('/\{\{|\}\}|\$[a-z_]|->|\)\s*"|"\s*required|"\s*min=|"\s*>|\? >/i', $text)) continue;
    if (preg_match('/^(price|process_name|process_rate|yarn_code|yarn_count|yarn_type|width) \}\}/', $text)) continue;
    // Sample data constants (product/yarn codes, emails, URLs)
    if (preg_match('/^[A-Z0-9\-]{5,}$/', $text) && !preg_match('/\s/', $text)) continue;
    if (preg_match('/\.com|@|http|ERP-|SKU-|SUP-|PO-|INV-/', $text)) continue;
    if ($text === '. Jurnal akan otomatis dibuat:') continue;
    $texts[$text] = true;
}
$out = '';
foreach (array_keys($texts) as $t) $out .= $t . "\n";
file_put_contents(__DIR__ . '/tmp_texts_only.txt', $out);
echo 'Unique filtered texts: ' . count($texts) . PHP_EOL;
