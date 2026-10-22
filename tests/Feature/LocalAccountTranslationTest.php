<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountTranslation;
use App\Services\AccountTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalAccountTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_translation_preserves_source_and_manual_translation(): void
    {
        config(['libretranslate.enabled' => true, 'libretranslate.url' => 'http://127.0.0.1:5001']);
        $account = Account::create(['account_code' => '111999', 'account_name' => 'Kas Gudang',
            'coa_type' => 'Cash', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        AccountTranslation::create(['account_code' => $account->getKey(), 'locale' => 'en',
            'name' => 'Warehouse cash', 'is_auto_translated' => false]);
        Http::fake(['127.0.0.1:5001/*' => Http::response(['translatedText' => '仓库现金'])]);
        app(AccountTranslationService::class)->translateAccountNames([$account->getKey()]);
        app(AccountTranslationService::class)->translateAccountNames([$account->getKey()]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:5001/translate'
            && $request['target'] === 'zh-Hans' && $request['q'] === 'Kas Gudang');
        $account->refresh();
        $this->assertSame('Kas Gudang', $account->account_name);
        $this->assertSame('Kas Gudang', $account->translatedName('id'));
        $this->assertSame('Warehouse cash', $account->translatedName('en'));
        $this->assertSame('仓库现金', $account->translatedName('zh_CN'));
        $this->assertFalse(AccountTranslation::where('locale', 'en')->first()->is_auto_translated);
    }

    public function test_local_failure_does_not_fall_back_to_external_api(): void
    {
        config(['libretranslate.enabled' => true, 'services.deepl.key' => 'not-used']);
        $account = Account::create(['account_code' => '111998', 'account_name' => 'Kas',
            'coa_type' => 'Cash', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        Http::fake(['*' => Http::response([], 503)]);
        try {
            app(AccountTranslationService::class)->translateAccountNames([$account->getKey()]);
            $this->fail('Service failure should be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('no external fallback', $exception->getMessage());
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('account_translations', 0);
    }
}