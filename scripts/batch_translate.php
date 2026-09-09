<?php

/**
 * Batch Translator untuk i18n
 * 
 * Cara pakai:
 * 1. Jalankan: php artisan i18n:scan-hardcoded
 * 2. Edit file hardcoded_report.json (hapus yang tidak perlu diterjemahkan)
 * 3. Jalankan: php scripts/batch_translate.php
 * 4. Review file translated_output.json
 * 5. Apply ke blade files: php artisan i18n:apply-translations
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BatchTranslator
{
    private $apiKey;
    private $apiEndpoint = 'https://api.anthropic.com/v1/messages';
    private $model = 'claude-3-5-sonnet-20241022';
    private $batchSize = 50;
    private $inputFile;
    private $outputFile;
    private $translations = [];

    public function __construct()
    {
        $this->apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$this->apiKey) {
            die("❌ Set environment variable ANTHROPIC_API_KEY first!\n");
        }

        $this->inputFile = __DIR__ . '/../hardcoded_report.json';
        $this->outputFile = __DIR__ . '/../translated_output.json';
    }

    public function run()
    {
        echo "🌐 Batch Translator for i18n\n";
        echo "============================\n\n";

        if (!File::exists($this->inputFile)) {
            die("❌ Input file tidak ditemukan: {$this->inputFile}\n");
            die("   Jalankan: php artisan i18n:scan-hardcoded\n");
        }

        $report = json_decode(File::get($this->inputFile), true);
        $strings = $report['hardcoded_strings'] ?? [];

        echo "📊 Ditemukan " . count($strings) . " hardcoded strings\n\n";

        // Filter: hanya yang belum ada di terjemahan
        $toTranslate = $this->filterAlreadyTranslated($strings);
        echo "🔍 Setelah filter: " . count($toTranslate) . " strings perlu diterjemahkan\n\n";

        if (empty($toTranslate)) {
            echo "✅ Tidak ada yang perlu diterjemahkan!\n";
            return;
        }

        // Batch translate
        $batches = array_chunk($toTranslate, $this->batchSize);
        $totalBatches = count($batches);

        echo "📦 Memproses " . $totalBatches . " batch ({$this->batchSize} per batch)\n\n";

        foreach ($batches as $index => $batch) {
            $batchNum = $index + 1;
            echo "[{$batchNum}/{$totalBatches}] Translating " . count($batch) . " strings...\n";
            
            $this->translateBatch($batch);
            
            // Progress bar
            $progress = ($batchNum / $totalBatches) * 100;
            echo str_repeat('█', (int)($progress / 5)) . str_repeat('░', 20 - (int)($progress / 5));
            echo " " . round($progress, 1) . "%\n\n";

            // Rate limiting (Anthropic: 50 requests/minute)
            if ($batchNum < $totalBatches) {
                echo "⏳ Waiting 2 seconds (rate limit)...\n";
                sleep(2);
            }
        }

        $this->saveOutput();
        $this->showSummary();
    }

private function filterAlreadyTranslated(array $strings)
    {
        $existing = [];
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            $file = base_path("lang/{$locale}/erp.php");
            if (File::exists($file)) {
                $data = include $file;
                array_walk_recursive($data, fn($v) => $existing[$v] = true);
            }
        }

        return array_filter($strings, fn($item) => !isset($existing[$item['text']]));
    }

    private function translateBatch(array $batch)
    {
        $texts = array_column($batch, 'text');
        
        $prompt = $this->buildPrompt($texts);
        $response = $this->callAPI($prompt);

        if (!$response) {
            echo "  ❌ API call failed, retrying in 5 seconds...\n";
            sleep(5);
            $response = $this->callAPI($prompt);
            
            if (!$response) {
                echo "  ❌ Failed again, skipping batch\n";
                return;
            }
        }

        $translations = $this->parseResponse($response);
        
        foreach ($batch as $item) {
            $text = $item['text'];
            $this->translations[$text] = [
                'id' => $text, // Indonesian is the source
                'en' => $translations['en'][$text] ?? '[MISSING]',
                'zh_CN' => $translations['zh_CN'][$text] ?? '[MISSING]',
                'key' => $this->generateKey($text),
            ];
        }
    }

    private function buildPrompt(array $texts)
    {
        $jsonTexts = json_encode($texts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a professional translator for an Indonesian ERP Accounting + Manufacturing system.

Translate the following Indonesian texts to English (en) and Simplified Chinese (zh_CN).

RULES:
1. Keep accounting terms in Indonesian when they are standard: DEBET, KREDIT, Neraca, Laba Rugi, HPP, COA, SPK, MRN
2. Translate UI labels naturally (buttons, menus, alerts, placeholders)
3. Use formal Simplified Chinese (简体中文), business tone
4. Preserve any placeholders like :attribute, :count, :name exactly as-is
5. Return ONLY valid JSON in this format:
{
  "en": {"Indonesian text 1": "English translation", ...},
  "zh_CN": {"Indonesian text 1": "Chinese translation", ...}
}

INPUT TEXTS (Indonesian):
{$jsonTexts}

OUTPUT (JSON only, no explanation):
PROMPT;
    }

    private function callAPI(string $prompt)
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "  ⚠️  API error (HTTP {$httpCode})\n";
            return null;
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? null;
    }

    private function parseResponse(string $response)
    {
        // Extract JSON from response (in case AI adds explanation)
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        echo "  ⚠️  Failed to parse JSON response\n";
        return ['en' => [], 'zh_CN' => []];
    }

    private function generateKey(string $text)
    {
        // Generate snake_case key from text
        $key = Str::slug($text, '_');
        $key = preg_replace('/[^a-z0-9_]/', '', $key);
        $key = trim($key, '_');
        
        // Prefix with 'ui_' if starts with number
        if (is_numeric($key[0] ?? '')) {
            $key = 'ui_' . $key;
        }

        return $key ?: 'ui_' . md5($text);
    }

    private function saveOutput()
    {
        File::put($this->outputFile, json_encode($this->translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "\n✅ Output saved to: {$this->outputFile}\n";
    }

    private function showSummary()
    {
        $total = count($this->translations);
        $missingEn = count(array_filter($this->translations, fn($t) => $t['en'] === '[MISSING]'));
        $missingZh = count(array_filter($this->translations, fn($t) => $t['zh_CN'] === '[MISSING]'));

        echo "\n📊 SUMMARY\n";
        echo "=========\n";
        echo "Total translated: {$total}\n";
        echo "Missing EN: {$missingEn}\n";
        echo "Missing ZH_CN: {$missingZh}\n";
        echo "\nNext steps:\n";
        echo "1. Review {$this->outputFile}\n";
        echo "2. Run: php artisan i18n:apply-translations\n";
    }
}

// Run
$translator = new BatchTranslator();
$translator->run();