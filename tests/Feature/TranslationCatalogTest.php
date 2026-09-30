<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationCatalogTest extends TestCase
{
    public function test_every_erp_key_used_by_the_application_exists_in_indonesian_english_and_chinese(): void
    {
        $keys = [];

        foreach (File::allFiles(base_path('app')) as $file) {
            $this->collectErpKeys(File::get($file->getPathname()), $keys);
        }

        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->collectErpKeys(File::get($file->getPathname()), $keys);
        }

        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $translations = require lang_path($locale.'/erp.php');

            foreach (array_keys($keys) as $key) {
                $this->assertArrayHasKey($key, $translations, "Missing erp.{$key} in {$locale}.");
                $this->assertNotSame('', trim((string) $translations[$key]), "Empty erp.{$key} in {$locale}.");
            }
        }
    }

    private function collectErpKeys(string $contents, array &$keys): void
    {
        preg_match_all('/(?:__|@lang|trans)\(\s*[\'\"]erp\.([A-Za-z][A-Za-z0-9_]*)(?<!key)[\'\"]/', $contents, $matches);

        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }
}
