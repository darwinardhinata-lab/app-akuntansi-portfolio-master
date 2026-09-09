<?php
/**
 * STEP 1: Extract & Categorize Hardcoded Strings
 * 
 * Mengkategorikan ke 4 tier:
 * - tier1_safe: HTML attributes (placeholder, title, aria-label) → 100% aman auto-replace
 * - tier2_simple: HTML text nodes sederhana → semi-auto dengan review
 * - tier3_complex: Nested HTML, JS strings, conditional → manual
 * - skip: Bukan UI text (code, constants, blade syntax)
 * 
 * Usage: php scripts/extract_and_categorize.php [--path=resources/views] [--verbose]
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ExtractAndCategorize
{
    private string $basePath;
    private bool $verbose;
    private array $existingValues = [];
    private array $categories = [
        'tier1_safe' => [],
        'tier2_simple' => [],
        'tier3_complex' => [],
        'skip' => [],
    ];
    private array $stats = [
        'files_scanned' => 0,
        'lines_scanned' => 0,
        'tier1_found' => 0,
        'tier2_found' => 0,
        'tier3_found' => 0,
        'skipped' => 0,
    ];
    private string $workspace;

    public function __construct(string $basePath, bool $verbose = false)
    {
        $this->basePath = base_path($basePath);
        $this->verbose = $verbose;
        $this->workspace = storage_path('i18n-workspace');
        
        if (!File::exists($this->workspace)) {
            File::makeDirectory($this->workspace, 0755, true);
        }
        if (!File::exists("{$this->workspace}/backups")) {
            File::makeDirectory("{$this->workspace}/backups", 0755, true);
        }
    }

    public function run(): void
    {
        $this->printHeader();
        $this->loadExistingTranslations();
        $this->scanDirectory();
        $this->exportResults();
        $this->printSummary();
    }

    private function printHeader(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║  STEP 1: Extract & Categorize Hardcoded Strings           ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        echo "📁 Base path: {$this->basePath}\n";
        echo "📂 Workspace: {$this->workspace}\n\n";
    }

    private function loadExistingTranslations(): void
    {
        echo "📚 Memuat terjemahan existing...\n";
        
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $file = base_path("lang/{$locale}/erp.php");
            if (!File::exists($file)) {
                echo "  ⚠️  File tidak ditemukan: {$file}\n";
                continue;
            }
            
            try {
                $data = include $file;
                if (!is_array($data)) continue;
                
                $count = 0;
                array_walk_recursive($data, function($v) use (&$count) {
                    if (is_string($v) && strlen(trim($v)) >= 3) {
                        $this->existingValues[trim($v)] = true;
                        $count++;
                    }
                });
                echo "  ✅ {$locale}: {$count} values loaded\n";
            } catch (\Throwable $e) {
                echo "  ❌ Error loading {$locale}: {$e->getMessage()}\n";
            }
        }
        
        echo "  📊 Total unique existing values: " . count($this->existingValues) . "\n\n";
    }

    private function scanDirectory(): void
    {
        if (!File::exists($this->basePath)) {
            throw new \RuntimeException("Path tidak ditemukan: {$this->basePath}");
        }

        $files = File::allFiles($this->basePath);
        $bladeFiles = array_filter($files, function($f) {
            return Str::endsWith($f->getFilename(), '.blade.php');
        });

        echo "🔍 Scanning " . count($bladeFiles) . " blade files...\n\n";

        $progress = 0;
        $total = count($bladeFiles);

        foreach ($bladeFiles as $file) {
            $progress++;
            $this->scanFile($file);
            
            if ($progress % 50 === 0 || $progress === $total) {
                $percent = round(($progress / $total) * 100);
                echo "\r  Progress: {$progress}/{$total} ({$percent}%)";
            }
        }
        
        echo "\n\n";
    }

    private function scanFile(\SplFileInfo $file): void
    {
        $this->stats['files_scanned']++;
        $content = File::get($file->getPathname());
        $lines = explode("\n", $content);
        $relativePath = Str::after($file->getPathname(), base_path() . '/');

        foreach ($lines as $lineNum => $line) {
            $this->stats['lines_scanned']++;
            $this->analyzeLine($line, $lineNum + 1, $relativePath);
        }
    }
private function analyzeLine(string $line, int $lineNum, string $file): void
    {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || str_starts_with($trimmedLine, '//') || str_starts_with($trimmedLine, '{{--')) return;
        $this->extractTier1($line, $lineNum, $file);
        $this->extractTextNodes($line, $lineNum, $file);
        $this->extractJavaScriptStrings($line, $lineNum, $file);
        $this->extractSkipPatterns($line, $lineNum, $file);
    }

    private function extractTier1(string $line, int $lineNum, string $file): void
    {
        $attributes = ['placeholder', 'title', 'aria-label', 'alt', 'data-original-title'];
        foreach ($attributes as $attr) {
            if (preg_match_all('/' . $attr . '=["\']([^"\']{3,200})["\']/', $line, $matches)) {
                foreach ($matches[1] as $text) {
                    $text = trim($text);
                    if (!$this->shouldSkipText($text)) {
                        $this->addToCategory('tier1_safe', $text, $file, $lineNum, $line, $attr);
                    }
                }
            }
        }
    }

    private function extractTextNodes(string $line, int $lineNum, string $file): void
    {
        if (!preg_match_all('/>([^<]{2,300})</', $line, $matches)) return;
        foreach ($matches[1] as $text) {
            $text = trim(strip_tags($text));
            if ($this->shouldSkipText($text)) continue;
            if (isset($this->existingValues[$text])) { $this->stats['skipped']++; continue; }
            if (preg_match('/<[a-z][^>]*>/i', $text)) {
                $this->addToCategory('tier3_complex', $text, $file, $lineNum, $line, 'nested_html'); continue;
            }
            if (preg_match('/\{\{|\{!!|@if|@foreach|@endphp|@isset|@empty/', $text)) {
                $this->addToCategory('skip', $text, $file, $lineNum, $line, 'blade_syntax'); continue;
            }
            if (preg_match('/\$[a-zA-Z_]|function\s*\(|=>|::|->|new\s+[A-Z]/', $text)) {
                $this->addToCategory('skip', $text, $file, $lineNum, $line, 'code_pattern'); continue;
            }
            if (!preg_match('/[a-zA-Z\x{4e00}-\x{9fff}]/u', $text)) {
                $this->addToCategory('skip', $text, $file, $lineNum, $line, 'no_letters'); continue;
            }
            if (strlen($text) < 3) continue;
            $this->addToCategory('tier2_simple', $text, $file, $lineNum, $line, 'simple_text');
        }
    }

private function extractJavaScriptStrings(string $line, int $lineNum, string $file): void
    {
        $patterns = [
            '/(?:alert|confirm|prompt)\s*\(\s*["\']([^"\']{3,200})["\']/',
            '/(?:title|text|html|confirmButtonText|cancelButtonText)\s*:\s*["\']([^"\']{3,200})["\']/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $line, $matches)) {
                foreach ($matches[1] as $text) {
                    $text = trim($text);
                    if ($this->shouldSkipText($text)) continue;
                    if (isset($this->existingValues[$text])) continue;
                    $this->addToCategory('tier3_complex', $text, $file, $lineNum, $line, 'javascript');
                }
            }
        }
    }

    private function extractSkipPatterns(string $line, int $lineNum, string $file): void
    {
        if (preg_match('/@(?:if|elseif|unless)\s*\([^)]*[\'"]([^\'"]{3,50})[\'"]/', $line, $m)) {
            $this->addToCategory('skip', $m[1], $file, $lineNum, $line, 'blade_condition');
        }
    }

    private function shouldSkipText(string $text): bool
    {
        if (isset($this->existingValues[$text])) return true;
        if (strlen($text) < 3) return true;
        if (!preg_match('/[a-zA-Z\x{4e00}-\x{9fff}]/u', $text)) return true;
        if (preg_match('/\{\{|\{!!|@if|@foreach|@endphp/', $text)) return true;
        return false;
    }

    private function addToCategory(string $category, string $text, string $file, int $line, string $context, string $reason = ''): void
    {
        if (!isset($this->categories[$category][$text])) {
            $this->categories[$category][$text] = ['text' => $text, 'occurrences' => [], 'file_count' => 0, 'reason' => $reason];
        }
        $this->categories[$category][$text]['occurrences'][] = ['file' => $file, 'line' => $line, 'context' => trim($context)];
        $this->categories[$category][$text]['file_count'] = count(array_unique(array_column($this->categories[$category][$text]['occurrences'], 'file')));
        $this->stats["{$category}_found"]++;
        if ($this->verbose) { echo "  [{$category}] {$text}\n    → {$file}:{$line}\n"; }
    }

    private function exportResults(): void
    {
        echo "💾 Exporting results...\n";
        foreach ($this->categories as $category => $data) {
            uasort($data, fn($a, $b) => $b['file_count'] <=> $a['file_count']);
            $outputFile = "{$this->workspace}/{$category}.json";
            File::put($outputFile, json_encode([
                'category' => $category, 'generated_at' => now()->toIso8601String(),
                'total_unique_texts' => count($data),
                'total_occurrences' => array_sum(array_map(fn($d) => count($d['occurrences']), $data)),
                'items' => array_values($data),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "  ✅ {$category}: " . count($data) . " unique texts → {$outputFile}\n";
        }
        File::put("{$this->workspace}/extraction_stats.json", json_encode(['generated_at' => now()->toIso8601String(), 'base_path' => $this->basePath, 'stats' => $this->stats], JSON_PRETTY_PRINT));
    }

    private function printSummary(): void
    {
        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║  EXTRACTION SUMMARY                                        ║\n";
        echo "╠════════════════════════════════════════════════════════════╣\n";
        printf("║  Files scanned     : %-38d ║\n", $this->stats['files_scanned']);
        printf("║  Lines scanned     : %-38d ║\n", $this->stats['lines_scanned']);
        printf("║  Tier 1 (safe)     : %-38d ║\n", $this->stats['tier1_found']);
        printf("║  Tier 2 (simple)   : %-38d ║\n", $this->stats['tier2_found']);
        printf("║  Tier 3 (complex)  : %-38d ║\n", $this->stats['tier3_found']);
        printf("║  Skipped           : %-38d ║\n", $this->stats['skipped']);
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        echo "📋 NEXT STEPS:\n";
        echo "  1. Translate Tier 1: php scripts/batch_translate.php --tier=tier1_safe\n";
        echo "  2. Apply Tier 1:     php scripts/apply_tier1_safe.php\n";
        echo "  3. Translate Tier 2: php scripts/batch_translate.php --tier=tier2_simple\n";
        echo "  4. Generate patches: php scripts/generate_tier2_patches.php\n";
        echo "  5. Review patches:   edit storage/i18n-workspace/tier2_patches_reviewed.json\n";
        echo "  6. Apply Tier 2:     php scripts/apply_tier2_reviewed.php\n";
        echo "  7. Verify:           php scripts/verify_final.php\n\n";
    }
}

// === CLI HANDLER ===
$options = getopt('', ['path:', 'verbose']);
$basePath = $options['path'] ?? 'resources/views';
$verbose = isset($options['verbose']);

try {
    $extractor = new ExtractAndCategorize($basePath, $verbose);
    $extractor->run();
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n   at {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
