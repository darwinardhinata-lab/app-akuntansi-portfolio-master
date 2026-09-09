<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckMissingTranslations extends Command
{
    protected $signature = 'lang:check-missing 
                            {--fix : Auto-fix missing keys by copying from fallback}
                            {--locale= : Check specific locale only}
                            {--verbose : Show detailed missing keys}';

    protected $description = 'Check for missing translation keys across all language files';

    private $translations = [];
    private $allKeys = [];
    private $missingKeys = [];

    public function handle()
    {
        $this->info('🔍 Checking translation completeness...');

        $locales = $this->option('locale') 
            ? [$this->option('locale')] 
            : ['id', 'en', 'zh_CN'];

        // Load all translations
        foreach ($locales as $locale) {
            $this->loadLocale($locale);
        }

        // Find all unique keys
        foreach ($this->translations as $locale => $data) {
            $this->extractKeys($data, '', $locale);
        }

        $this->allKeys = array_unique($this->allKeys);
        sort($this->allKeys);

        $this->info("📊 Total unique keys: " . count($this->allKeys));

        // Check missing keys per locale
        foreach ($locales as $locale) {
            $this->checkMissing($locale);
        }

        $this->showSummary();

        if ($this->option('fix')) {
            $this->fixMissingKeys();
        }

        return count($this->missingKeys) > 0 ? 1 : 0;
    }

    private function loadLocale(string $locale)
    {
        $file = base_path("lang/{$locale}/erp.php");
        
        if (!File::exists($file)) {
            $this->warn("⚠️  File tidak ditemukan: {$file}");
            $this->translations[$locale] = [];
            return;
        }

        $this->translations[$locale] = include $file;
    }

    private function extractKeys(array $array, string $prefix, string $locale)
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $this->extractKeys($value, $fullKey, $locale);
            } else {
                $this->allKeys[] = $fullKey;
            }
        }
    }

    private function checkMissing(string $locale)
    {
        $flat = [];
        $this->flattenForCheck($this->translations[$locale], '', $flat);

        $missing = array_diff($this->allKeys, array_keys($flat));

        if (count($missing) > 0) {
            $this->missingKeys[$locale] = $missing;

            if ($this->option('verbose')) {
                $this->error("❌ {$locale}: Missing " . count($missing) . " keys");
                foreach (array_slice($missing, 0, 10) as $key) {
                    $this->line("   - {$key}");
                }
                if (count($missing) > 10) {
                    $this->line("   ... and " . (count($missing) - 10) . " more");
                }
            }
        }
    }

    private function flattenForCheck(array $array, string $prefix, array &$result)
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $this->flattenForCheck($value, $fullKey, $result);
            } else {
                $result[$fullKey] = $value;
            }
        }
    }

    private function showSummary()
    {
        $this->newLine();
        $this->info('📊 COMPLETENESS SUMMARY');

        $rows = [];
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $missing = count($this->missingKeys[$locale] ?? []);
            $total = count($this->allKeys);
            $complete = $total - $missing;
            $percent = $total > 0 ? round(($complete / $total) * 100, 1) : 0;

            $rows[] = [
                $locale,
                $complete,
                $missing,
                $total,
                "{$percent}%",
                $missing === 0 ? '✅' : '❌',
            ];
        }

        $this->table(
            ['Locale', 'Complete', 'Missing', 'Total', 'Progress', 'Status'],
            $rows
        );

        $totalMissing = array_sum(array_map('count', $this->missingKeys));
        if ($totalMissing > 0) {
            $this->warn("⚠️  Total {$totalMissing} missing keys across all locales");
            if (!$this->option('fix')) {
                $this->line("   Run with --fix to auto-copy from fallback (id)");
            }
        } else {
            $this->info("✅ All translations are complete!");
        }
    }

    private function fixMissingKeys()
    {
        $this->info('🔧 Auto-fixing missing keys...');

        $fallback = $this->translations['id'] ?? [];
        $flatFallback = [];
        $this->flattenForCheck($fallback, '', $flatFallback);

        foreach ($this->missingKeys as $locale => $missing) {
            if ($locale === 'id') continue; // Don't fix fallback

            $file = base_path("lang/{$locale}/erp.php");
            $data = $this->translations[$locale];

            foreach ($missing as $key) {
                $value = $flatFallback[$key] ?? "[MISSING: {$key}]";
                $this->setNestedValue($data, $key, $value);
            }

            // Write back to file
            $export = var_export($data, true);
            $export = str_replace(['array (', ')'], ['[', ']'], $export);
            File::put($file, "<?php\n\nreturn {$export};\n");

            $this->info("✅ Fixed " . count($missing) . " keys in {$locale}");
        }
    }

    private function setNestedValue(array &$array, string $key, $value)
    {
        $keys = explode('.', $key);
        $ref = &$array;

        foreach ($keys as $k) {
            if (!isset($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }

        $ref = $value;
    }
}
