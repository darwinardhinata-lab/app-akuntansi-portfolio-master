<?php
// Fresh scan of hardcoded display texts in blade files
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

// Build set of existing translation values (en, id, zh)
function collectValues($arr, &$set) {
    foreach ($arr as $v) {
        if (is_array($v)) collectValues($v, $set);
        else $set[$v] = true;
    }
}
$existingVals = [];
collectValues($enData, $existingVals);
collectValues($idData, $existingVals);
collectValues($zhData, $existingVals);

// Also collect key names (slug form)
$existingKeys = [];
collectKeys($enData, $existingKeys);
collectKeys($zhData, $existingKeys);
function collectKeys($arr, &$set, $prefix = '') {
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? $k : $prefix . '.' . $k;
        if (is_array($v)) collectKeys($v, $set, $key);
        else $set[$key] = true;
    }
}

$textsByFile = [];
$allTexts = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    $shortFile = str_replace(__DIR__ . '/resources/views/', '', $file);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
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
                if (preg_match('/\?|where\(|select\(|order\(|class=|\.e\(|->|__|url\(|route\(|csrf|\$/', $text)) continue;
                if (preg_match('/style=|src=|href=|data-|attr\(|function|\$\(/', $text)) continue;
                if (strlen($text) > 200) continue;
                if (preg_match('/^[A-Z]+$/', $text) && strlen($text) < 8) continue;
                if (preg_match('/^@\w+/', $text)) continue;
                if (isset($existingVals[$text])) continue;

                $allTexts[$text] = true;
                $textsByFile[$text][] = $shortFile;
            }
        }

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
    $files_str = implode(', ', array_slice(array_unique($textsByFile[$text]), 0, 3));
    $out .= $text . " || " . $files_str . "\n";
}
$out .= "===END===\n";

file_put_contents(__DIR__ . '/tmp_hardcoded_current.txt', $out);
echo $out;
