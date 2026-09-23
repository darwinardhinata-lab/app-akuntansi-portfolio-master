<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTranslation;
use App\Models\CoaTypeTranslation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountTranslationService
{
    /**
     * Mapping locale internal aplikasi -> kode target_lang DeepL.
     * FIX: 'en' HARUS 'EN-US'/'EN-GB' di DeepL -- 'EN' saja cuma valid
     * sebagai source_lang, bukan target_lang, request akan ditolak API.
     * 'id' sengaja tidak masuk sini -- locale default tidak pernah ditranslate,
     * accounts.account_name / accounts.coa_type sudah jadi sumber untuk locale itu.
     */
    protected const LOCALE_TO_DEEPL_TARGET = [
        'en'    => 'EN-US',
        'zh_CN' => 'ZH',
    ];

    /** DeepL membatasi jumlah teks per request -- kirim per batch kecil. */
    protected const BATCH_CHUNK_SIZE = 50;

    /**
     * Terjemahkan account_name untuk akun-akun yang diberikan, hanya untuk
     * pasangan (account_code, locale) yang belum ada di account_translations.
     *
     * @param  string[]  $accountCodes
     * @param  string[]  $locales  default: semua locale non-default
     */
    public function translateAccountNames(array $accountCodes, array $locales = ['en', 'zh_CN']): void
    {
        if (empty($accountCodes)) {
            return;
        }

        $accounts = Account::whereIn('account_code', $accountCodes)->get(['account_code', 'account_name']);

        foreach ($locales as $locale) {
            $existing = AccountTranslation::query()
                ->where('locale', $locale)
                ->whereIn('account_code', $accountCodes)
                ->pluck('account_code')
                ->all();

            $pending = $accounts->whereNotIn('account_code', $existing);

            if ($pending->isEmpty()) {
                continue;
            }

            $sourceMap = $pending->pluck('account_name', 'account_code')->all();
            $translated = $this->translateBatch($sourceMap, $locale);

            foreach ($translated as $accountCode => $name) {
                AccountTranslation::updateOrCreate(
                    ['account_code' => $accountCode, 'locale' => $locale],
                    [
                        'name'               => $name,
                        'is_auto_translated' => true,
                        'translated_at'      => now(),
                    ]
                );
            }
        }
    }

    /**
     * Terjemahkan nilai coa_type yang belum punya kamus untuk locale terkait.
     * Beroperasi di level nilai DISTINCT, bukan per-akun -- lihat catatan di
     * migration create_coa_type_translations_table.
     *
     * @param  string[]  $coaTypes  nilai coa_type mentah (locale id) yang perlu dicek
     * @param  string[]  $locales
     */
    public function translateCoaTypes(array $coaTypes, array $locales = ['en', 'zh_CN']): void
    {
        $coaTypes = array_values(array_unique(array_filter($coaTypes)));

        if (empty($coaTypes)) {
            return;
        }

        foreach ($locales as $locale) {
            $existing = CoaTypeTranslation::query()
                ->where('locale', $locale)
                ->whereIn('coa_type', $coaTypes)
                ->pluck('coa_type')
                ->all();

            $pending = array_values(array_diff($coaTypes, $existing));

            if (empty($pending)) {
                continue;
            }

            // Tidak ada id numerik untuk coa_type, pakai value itu sendiri sebagai key
            $sourceMap = array_combine($pending, $pending);
            $translated = $this->translateBatch($sourceMap, $locale);

            foreach ($translated as $originalCoaType => $label) {
                CoaTypeTranslation::updateOrCreate(
                    ['coa_type' => $originalCoaType, 'locale' => $locale],
                    [
                        'label'              => $label,
                        'is_auto_translated' => true,
                        'translated_at'      => now(),
                    ]
                );
            }
        }
    }

    /**
     * Panggil DeepL Free API untuk menerjemahkan satu batch string sekaligus.
     * DeepL menerima array `text[]` dan membalas array `translations[]` dengan
     * URUTAN YANG SAMA -- jadi kita kirim berdasarkan array_keys($sourceMap)
     * lalu map balik index->key, TANPA perlu parsing JSON bebas dari model AI
     * (lebih murah & jauh lebih reliable dibanding pendekatan prompt LLM).
     *
     * @param  array<string,string>  $sourceMap  key (account_code atau coa_type) => teks asal (locale id)
     * @return array<string,string>  key yang sama => hasil terjemahan
     */
    protected function translateBatch(array $sourceMap, string $locale): array
    {
        $apiKey = config('services.deepl.key');

        if (empty($apiKey)) {
            Log::warning('AccountTranslationService: DEEPL_API_KEY belum di-set, translate dilewati.', [
                'locale' => $locale,
                'count'  => count($sourceMap),
            ]);
            return [];
        }

        $targetLang = self::LOCALE_TO_DEEPL_TARGET[$locale] ?? null;
        if (!$targetLang) {
            Log::warning('AccountTranslationService: locale tidak dikenali DeepL.', ['locale' => $locale]);
            return [];
        }

        // FIX: key API free tier DeepL berakhiran ":fx" dan HARUS lewat host
        // api-free.deepl.com (bukan api.deepl.com yang khusus akun Pro/berbayar).
        $endpoint = config('services.deepl.endpoint', 'https://api-free.deepl.com/v2/translate');

        $keys = array_keys($sourceMap);
        $result = [];

        foreach (array_chunk($keys, self::BATCH_CHUNK_SIZE) as $chunkKeys) {
            $texts = array_map(fn ($k) => $sourceMap[$k], $chunkKeys);

            try {
                $response = Http::withHeaders([
                        'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
                        'Content-Type'  => 'application/json',
                    ])
                    ->timeout(30)
                    ->post($endpoint, [
                        'text'        => $texts,
                        'source_lang' => 'ID',
                        'target_lang' => $targetLang,
                    ]);

                if (!$response->successful()) {
                    Log::error('AccountTranslationService: DeepL API call gagal.', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    continue;
                }

                $translations = $response->json('translations', []);

                foreach ($chunkKeys as $i => $key) {
                    $result[$key] = $translations[$i]['text'] ?? $sourceMap[$key];
                }
            } catch (\Throwable $e) {
                Log::error('AccountTranslationService: exception saat translate via DeepL.', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }
}
