<?php
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app'));
foreach ($it as $f) {
    if ($f->getExtension() === 'php') {
        $path = $f->getPathname();
        $out = shell_exec('php -l "' . addslashes($path) . '" 2>&1');
        if (strpos($out, 'No syntax errors') === false) {
            echo $path . ': ' . $out . "\n";
        }
    }
}