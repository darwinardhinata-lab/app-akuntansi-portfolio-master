<?php
// Carefully extract all unique display text from blade files
$base = __DIR__ . '/resources/views';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($it as $f) {
    if ($f->getExtension() === 'php' && strpos($f->getFilename(), '.blade') !== false) {
        $files[] = $f->getPathname();
    }
}

$enData = include __DIR__ . '/lang/en/erp.php';
$idData = include __DIR__ . '/lang/id/erp.php';
$zhData = include __DIR__ . '/lang/zh_CN/erp.php';

// Build set of existing translation values
$existingVals = [];
foreach ($enData as $v) $existingVals[$v] = true;
foreach ($idData as $v) $existingVals[$v] = true;
foreach ($zhData as $v) $existingVals[$v] = true;

$textsByFile = [];
$allTexts = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $shortFile = str_replace(__DIR__ . '/resources/views/', '', $file);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        // Extract text between > and < (visible text in HTML)
        if (preg_match_all('/>([^<]+)</', $line, $matches)) {
            foreach ($matches[1] as $raw) {
                $text = trim($raw);
                if (strlen($text) < 3) continue;
                if (strpos($text, '{{') !== false) continue;
                if (strpos($text, '{!!') !== false) continue;
                if (preg_match('/^[0-9\s\-\+]+$/', $text)) continue;
                if (!preg_match('/[a-zA-Z]/', $text)) continue;
                if (preg_match('/^[\$\{\(]/', $text)) continue;
                if (preg_match('/^[\s]*[\#\.]/', $text)) continue;
                // Skip code-like strings
                if (preg_match('/\?|where\(|select\(|order\(|class=|\.e\(|->|__|url\(|route\(|csrf|\$/', $text)) continue;
                if (preg_match('/style=|src=|href=|data-|attr\(|function|\$\(/', $text)) continue;
                if (strlen($text) > 200) continue;
                // Skip single uppercase words (constants like ADMIN, STAFF)
                if (preg_match('/^[A-Z]+$/', $text) && strlen($text) < 8) continue;
                // Skip if starts with @ (blade directives)
                if (preg_match('/^@\w+/', $text)) continue;
                // Skip if already in existing translations
                if (isset($existingVals[$text])) continue;
                
                $allTexts[$text] = true;
                $textsByFile[$text][] = $shortFile;
            }
        }
        
        // Also catch text in quoted echo: {{ 'some text' }}
        if (preg_match_all('/\{\{\s*[\'\"]([^\'\"]+)[\'\"]\s*\}\}/', $line, $strMatches)) {
            foreach ($strMatches[1] as $str) {
                $str = trim($str);
                if (strlen($str) < 3) continue;
                if (preg_match('/^[a-z_]+$/', $str)) continue;
                if (strpos($str, 'erp.') !== false) continue;
                if (isset($existingVals[$str])) continue;
                $allTexts[$str] = true;
                $textsByFile[$str][] = $shortFile;
            }
        }
        
        // Catch hardcoded text in HTML attributes like title="text" or aria-label="text"
        if (preg_match_all('/(?:title|aria-label)=[\'\"]([^\'\"]+)[\'\"]/', $line, $attrMatches)) {
            foreach ($attrMatches[1] as $attr) {
                $attr = trim($attr);
                if (strlen($attr) < 3) continue;
                if (preg_match('/^[a-z_]+$/', $attr)) continue;
                if (isset($existingVals[$attr])) continue;
                $allTexts[$attr] = true;
                $textsByFile[$attr][] = $shortFile;
            }
        }
        
        // Catch placeholder="text"
        if (preg_match_all('/placeholder=[\'\"]([^\'\"]+)[\'\"]/', $line, $phMatches)) {
            foreach ($phMatches[1] as $ph) {
                $ph = trim($ph);
                if (strlen($ph) < 3) continue;
                if (preg_match('/^[a-z_]+$/', $ph)) continue;
                if (isset($existingVals[$ph])) continue;
                $allTexts[$ph] = true;
                $textsByFile[$ph][] = $shortFile;
            }
        }
    }
}

$sorted = array_keys($allTexts);
natcasesort($sorted);

$out = "Total: " . count($sorted) . "\n";
$out .= "===START===\n";
foreach ($sorted as $text) {
    $files_str = implode(', ', array_slice(array_unique($textsByFile[$text]), 0, 2));
    $out .= $text . " || " . $files_str . "\n";
}
$out .= "===END===\n";

file_put_contents(__DIR__ . '/tmp_translatable_texts.txt', $out);
echo $out;
