<?php

namespace App\Services;

use App\Models\JournalHeader;
use App\Models\JournalNoteTranslation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LibreTranslateService
{
    public static function fingerprint(string $text): string
    {
        return hash('sha256', json_encode([config('libretranslate.source'), config('libretranslate.version'), config('libretranslate.targets'), $text], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function endpoint(): string
    {
        $url = rtrim((string) config('libretranslate.url'), '/');
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || !in_array($parts['host'] ?? '', ['127.0.0.1', '[::1]'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('LibreTranslate pilot requires a loopback URL without credentials or path.');
        }
        return $url;
    }

    public function translateJournal(string $journalId): void
    {
        if (!config('libretranslate.enabled')) {
            return;
        }
        $journal = JournalHeader::find($journalId);
        $text = (string) $journal?->notes;
        if (!$journal || trim($text) === '' || mb_strlen($text) > config('libretranslate.max_characters')) {
            return;
        }
        $url = $this->endpoint();
        $hash = self::fingerprint($text);
        foreach (config('libretranslate.targets') as $locale => $target) {
            $key = ['journal_id' => $journalId, 'locale' => $locale, 'source_hash' => $hash];
            if (JournalNoteTranslation::where($key)->exists()) {
                continue;
            }
            // No redirect, public endpoint, or external fallback; do not log API bodies.
            $response = Http::connectTimeout(3)->timeout(60)->withoutRedirecting()->post($url.'/translate', [
                'q' => $text, 'source' => config('libretranslate.source'), 'target' => $target, 'format' => 'text',
            ]);
            if (!$response->successful()) {
                throw new RuntimeException('Local LibreTranslate HTTP status '.$response->status());
            }
            $translated = $response->json('translatedText');
            if (!is_string($translated) || trim($translated) === '') {
                throw new RuntimeException('Invalid local LibreTranslate response.');
            }
            // Discard results if source changed or the record disappeared during the HTTP call.
            $current = JournalHeader::find($journalId);
            if (!$current || self::fingerprint((string) $current->notes) !== $hash) {
                return;
            }
            JournalNoteTranslation::firstOrCreate($key, ['translated_text' => $translated]);
        }
    }
}