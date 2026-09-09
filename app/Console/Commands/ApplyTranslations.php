<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApplyTranslations extends Command
{
    protected $signature = 'i18n:apply-translations 
                            {--dry-run : Preview changes without applying}
                            {--backup : Create backup before applying}';

    protected $description = 'Apply translations from translated_output.json to blade files and language files';

    private $translations;
    private $stats = [
        'blades_updated' => 0,
        'replacements_made' => 0,
        'keys_added' => 0,
    ];

    public function handle()
    {
        $this->info('🚀 Applying translations...');

        $inputFile = base_path('translated_output.json');
        if (!File::exists($inputFile)) {
            $this->error("❌ File tidak ditemukan: {$inputFile}");
            $this->line("   Jalankan: php scripts/batch_translate.php");
            return 1;
        }

        $this->translations = json_decode(File::get($inputFile), true);
        
        if (empty($this->translations)) {
            $this->warn("⚠️  Tidak ada terjemahan untuk di-apply");
            return 0;
        }

        $this->info("📊 Found " . count($this->translations) . " translations");

        if ($this->option('dry-run')) {
            $this->info("🔍 DRY RUN MODE - no changes will be made\n");
        }

        if ($this->option('backup') && !$this->option('dry-run')) {
            $this->createBackup();
        }

        $this->updateLanguageFiles();
        $this->updateBladeFiles();
        $this->showSummary();

        return 0;
    }

    private function createBackup()
    {
        $timestamp = date('Ymd_His');
        $backupDir = base_path("storage/backups/i18n_{$timestamp}");
        File::makeDirectory($backupDir, 0755, true);

        // Backup language files
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $file = base_path("lang/{$locale}/erp.php");
            if (File::exists($file)) {
                File::copy($file, "{$backupDir}/erp_{$locale}.php");
            }
        }

        // Backup blade files (only those that will be modified)
        $bladeFiles = $this->getAffectedBladeFiles();
        foreach ($bladeFiles as $file) {
            $relativePath = Str::after($file, base_path() . '/');
            $backupPath = "{$backupDir}/views/{$relativePath}";
            File::makeDirectory(dirname($backupPath), 0755, true);
            File::copy($file, $backupPath);
        }

        $this->info("✅ Backup created: {$backupDir}");
    }

    private function getAffectedBladeFiles()
    {
        $files = [];
        $scanReport = base_path('hardcoded_report.json');
        
        if (File::exists($scanReport)) {
            $report = json_decode(File::get($scanReport), true);
            foreach ($report['hardcoded_strings'] ?? [] as $item) {
                foreach ($item['files'] ?? [] as $file) {
                    $files[] = base_path($file);
                }
            }
        }

        return array_unique($files);
    }

    private function updateLanguageFiles()
    {
        $this->info("\n📝 Updating language files...");

        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $file = base_path("lang/{$locale}/erp.php");
            $data = File::exists($file) ? include $file : [];

            $added = 0;
            foreach ($this->translations as $text => $trans) {
                $key = $trans['key'];
                $value = $trans[$locale] ?? $text;

                if (!isset($data[$key])) {
                    $data[$key] = $value;
                    $added++;
                }
            }

            if ($added > 0 && !$this->option('dry-run')) {
                $export = var_export($data, true);
                $export = str_replace(['array (', ')'], ['[', ']'], $export);
                File::put($file, "<?php\n\nreturn {$export};\n");
            }

            $this->stats['keys_added'] += $added;
            $this->line("  {$locale}: +{$added} keys");
        }
    }

    private function updateBladeFiles()
    {
        $this->info("\n🎨 Updating blade files...");

        $files = $this->getAffectedBladeFiles();
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $file) {
            if (!File::exists($file)) {
                $bar->advance();
                continue;
            }

            $content = File::get($file);
            $replacements = 0;

            foreach ($this->translations as $text => $trans) {
                $key = $trans['key'];
                $escaped = preg_quote($text, '/');

                // Replace >Text< → >{{ __('erp.key') }}<
                $content = preg_replace(
                    '/>(\s*)' . $escaped . '(\s*)</',
                    '>$1{{ __(\'erp.' . $key . '\') }}$2<',
                    $content, -1, $count1
                );
                $replacements += $count1;

                // Replace placeholder="Text"
                $content = preg_replace(
                    '/placeholder="' . $escaped . '"/',
                    'placeholder="{{ __(\'erp.' . $key . '\') }}"',
                    $content, -1, $count2
                );
                $replacements += $count2;

                // Replace title="Text"
                $content = preg_replace(
                    '/title="' . $escaped . '"/',
                    'title="{{ __(\'erp.' . $key . '\') }}"',
                    $content, -1, $count3
                );
                $replacements += $count3;
            }

            if ($replacements > 0) {
                if (!$this->option('dry-run')) {
                    File::put($file, $content);
                }
                $this->stats['blades_updated']++;
                $this->stats['replacements_made'] += $replacements;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function showSummary()
    {
        $this->newLine();
        $this->info('📊 SUMMARY');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Blade Files Updated', $this->stats['blades_updated']],
                ['Total Replacements', $this->stats['replacements_made']],
                ['Language Keys Added', $this->stats['keys_added']],
            ]
        );

        if ($this->option('dry-run')) {
            $this->warn("🔍 DRY RUN complete - no changes were made");
            $this->line("   Run without --dry-run to apply changes");
        } else {
            $this->info("✅ All translations applied successfully!");
            $this->line("   Run: php artisan lang:check-missing to verify");
        }
    }
}
