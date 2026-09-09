<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ScanHardcodedStrings extends Command
{
    protected $signature = 'i18n:scan-hardcoded 
                            {--path=resources/views : Path to scan}
                            {--output=hardcoded_report.json : Output file}
                            {--verbose : Show detailed output}';

    protected $description = 'Scan blade files for hardcoded strings that should be translated';

    private $existingTranslations = [];
    private $hardcodedStrings = [];
    private $stats = [
        'files_scanned' => 0,
        'hardcoded_found' => 0,
        'already_translated' => 0,
    ];

    public function handle()
    {
        $this->info('🔍 Scanning for hardcoded strings...');
        $this->loadExistingTranslations();

        $path = base_path($this->option('path'));
        if (!File::exists($path)) {
            $this->error("❌ Path tidak ditemukan: {$path}");
            return 1;
        }

        $files = File::allFiles($path);
        $bladeFiles = array_filter($files, fn($f) => Str::endsWith($f->getFilename(), '.blade.php'));

        $this->info("📁 Ditemukan " . count($bladeFiles) . " file blade");

        $bar = $this->output->createProgressBar(count($bladeFiles));
        $bar->start();

        foreach ($bladeFiles as $file) {
            $this->scanFile($file);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->generateReport();
        $this->showSummary();

        return 0;
    }

    private function loadExistingTranslations()
    {
        $this->info('📚 Memuat terjemahan existing...');
        
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $file = base_path("lang/{$locale}/erp.php");
            if (File::exists($file)) {
                $data = include $file;
                $this->flattenArray($data, '', $locale);
            }
        }

        $count = count($this->existingTranslations['id'] ?? []);
        $this->info("✅ Loaded {$count} keys");
    }

    private function flattenArray(array $array, string $prefix, string $locale)
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $this->flattenArray($value, $fullKey, $locale);
            } else {
                $this->existingTranslations[$locale][$fullKey] = $value;
                $this->existingTranslations['by_value'][$value] = $fullKey;
            }
        }
    }

    private function scanFile(\SplFileInfo $file)
    {
        $this->stats['files_scanned']++;
        $content = File::get($file->getPathname());
        $relativePath = Str::after($file->getPathname(), base_path() . '/');

        // Pattern 1: HTML text nodes >Text<
        preg_match_all('/>([^<]{3,200})</', $content, $matches);
        foreach ($matches[1] as $text) {
            $this->processText($text, $relativePath, 'html_text');
        }

        // Pattern 2: placeholder="..."
        preg_match_all('/placeholder=["\']([^"\']{3,200})["\']/', $content, $matches);
        foreach ($matches[1] as $text) {
            $this->processText($text, $relativePath, 'placeholder');
        }

        // Pattern 3: title="..."
        preg_match_all('/title=["\']([^"\']{3,200})["\']/', $content, $matches);
        foreach ($matches[1] as $text) {
            $this->processText($text, $relativePath, 'title');
        }

        // Pattern 4: aria-label="..."
        preg_match_all('/aria-label=["\']([^"\']{3,200})["\']/', $content, $matches);
        foreach ($matches[1] as $text) {
            $this->processText($text, $relativePath, 'aria_label');
        }
    }

    private function processText(string $text, string $file, string $type)
    {
        $text = trim(strip_tags($text));
        
        // Skip if too short
        if (strlen($text) < 3) return;
        
        // Skip if contains Blade syntax
        if (preg_match('/\{\{!!|@if|@foreach|@endphp/', $text)) return;
        
        // Skip if contains PHP/JS code patterns
        if (preg_match('/^[\s]*[\#\.@\$]|where\(|route\(|function\(|var |let |const /', $text)) return;
        
        // Skip if no letters (only numbers/symbols)
        if (!preg_match('/[a-zA-Z\x{4e00}-\x{9fff}]/u', $text)) return;
        
        // Check if already translated
        if (isset($this->existingTranslations['by_value'][$text])) {
            $this->stats['already_translated']++;
            return;
        }

        // It's hardcoded!
        $this->stats['hardcoded_found']++;
        
        if (!isset($this->hardcodedStrings[$text])) {
            $this->hardcodedStrings[$text] = [
                'text' => $text,
                'files' => [],
                'types' => [],
                'occurrences' => 0,
            ];
        }
        
        $this->hardcodedStrings[$text]['files'][] = $file;
        $this->hardcodedStrings[$text]['types'][] = $type;
        $this->hardcodedStrings[$text]['occurrences']++;

        if ($this->option('verbose')) {
            $this->line("  ⚠️  [{$type}] {$text}");
            $this->line("     → {$file}");
        }
    }

    private function generateReport()
    {
        $outputFile = base_path($this->option('output'));
        
        // Sort by occurrences (most common first)
        uasort($this->hardcodedStrings, fn($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'summary' => $this->stats,
            'hardcoded_strings' => array_values($this->hardcodedStrings),
        ];

        File::put($outputFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("✅ Report saved to: {$outputFile}");
    }

    private function showSummary()
    {
        $this->newLine();
        $this->info('📊 SUMMARY');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files Scanned', $this->stats['files_scanned']],
                ['Hardcoded Strings Found', $this->stats['hardcoded_found']],
                ['Already Translated', $this->stats['already_translated']],
                ['Unique Hardcoded Texts', count($this->hardcodedStrings)],
            ]
        );

        if ($this->stats['hardcoded_found'] > 0) {
            $this->warn("⚠️  Found {$this->stats['hardcoded_found']} hardcoded strings that need translation!");
            $this->line("   Run: php scripts/batch_translate.php to translate them");
        } else {
            $this->info("✅ No hardcoded strings found!");
        }
    }
}
