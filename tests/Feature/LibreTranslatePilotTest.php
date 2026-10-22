<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\JournalNoteTranslation;
use App\Services\LibreTranslateService;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Exports\LedgerExport;
use App\Exports\JournalExport;
use App\Jobs\TranslateJournalNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreTranslatePilotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['libretranslate.url' => 'http://127.0.0.1:5000', 'libretranslate.targets' => ['en' => 'en', 'zh_CN' => 'zh']]);
    }

    public function test_alternate_chinese_code_is_sent_and_invalidates_cached_source_version(): void
    {
        $journal = $this->journal();
        $oldHash = LibreTranslateService::fingerprint($journal->notes);
        config(['libretranslate.targets.zh_CN' => 'zh-Hans']);
        $this->assertNotSame($oldHash, LibreTranslateService::fingerprint($journal->notes));
        Http::fake(['127.0.0.1:5000/*' => Http::response(['translatedText' => 'Translation'])]);
        app(LibreTranslateService::class)->translateJournal($journal->getKey());
        Http::assertSent(fn ($request) => $request['target'] === 'zh-Hans');
        $this->assertDatabaseCount('journal_note_translations', 2);
    }

    private function journal(): JournalHeader
    {
        config(['libretranslate.enabled' => false]);
        $journal = JournalHeader::create(['transaction_date' => '2026-10-01', 'notes' => 'Pembayaran sewa gudang.']);
        config(['libretranslate.enabled' => true]);
        return $journal;
    }

    public function test_local_translation_is_cached_per_language_and_does_not_change_source(): void
    {
        Http::preventStrayRequests();
        Http::fake(['127.0.0.1:5000/*' => Http::response(['translatedText' => 'Translated'])]);
        $journal = $this->journal();
        $service = app(LibreTranslateService::class);
        $service->translateJournal($journal->getKey());
        $service->translateJournal($journal->getKey());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['source'] === 'id' && $request['target'] === 'zh' && $request['format'] === 'text');
        $this->assertDatabaseCount('journal_note_translations', 2);
        $this->assertSame('Pembayaran sewa gudang.', $journal->fresh()->notes);
    }

    public function test_disabled_feature_sends_nothing(): void
    {
        Http::fake();
        config(['libretranslate.enabled' => false]);
        app(LibreTranslateService::class)->translateJournal('missing');
        Http::assertNothingSent();
    }

    public function test_empty_and_oversized_notes_do_not_call_local_service(): void
    {
        $journal = $this->journal();
        Http::fake();
        foreach (['   ', str_repeat('a', 5001)] as $note) {
            config(['libretranslate.enabled' => false]);
            $journal->update(['notes' => $note]);
            config(['libretranslate.enabled' => true]);
            app(LibreTranslateService::class)->translateJournal($journal->getKey());
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('journal_note_translations', 0);
    }

    public function test_check_command_rejects_missing_chinese_route(): void
    {
        Http::fake([
            '127.0.0.1:5000/health' => Http::response(['status' => 'ok']),
            '127.0.0.1:5000/languages' => Http::response([['code' => 'id', 'targets' => ['en']]]),
        ]);
        $this->artisan('translation:journal --check')->assertFailed();
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_public_endpoint_is_rejected_before_sending_notes(): void
    {
        Http::fake();
        $journal = $this->journal();
        config(['libretranslate.url' => 'https://libretranslate.com']);
        try {
            app(LibreTranslateService::class)->translateJournal($journal->getKey());
            $this->fail('Public endpoint accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('loopback', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_invalid_response_is_not_cached_or_included_in_error(): void
    {
        $journal = $this->journal();
        Http::fake(['*' => Http::response(['error' => 'Sensitive provider message'], 500)]);
        try {
            app(LibreTranslateService::class)->translateJournal($journal->getKey());
            $this->fail('Failed response accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Local LibreTranslate HTTP status 500', $exception->getMessage());
        }
        $this->assertDatabaseCount('journal_note_translations', 0);
    }

    public function test_changed_source_during_request_discards_result(): void
    {
        $journal = $this->journal();
        Http::fake(function () use ($journal) {
            config(['libretranslate.enabled' => false]);
            $journal->update(['notes' => 'Keterangan baru']);
            config(['libretranslate.enabled' => true]);
            return Http::response(['translatedText' => 'Outdated']);
        });
        app(LibreTranslateService::class)->translateJournal($journal->getKey());
        $this->assertDatabaseCount('journal_note_translations', 0);
    }

    public function test_display_uses_current_version_and_retains_raw_description_for_editing(): void
    {
        $journal = $this->journal();
        Http::fake(['*' => Http::response(['translatedText' => 'Warehouse rent payment.'])]);
        app(LibreTranslateService::class)->translateJournal($journal->getKey());
        app()->setLocale('en');
        $this->assertSame('Warehouse rent payment.', $journal->fresh()->display_notes);
        $this->assertSame('Pembayaran sewa gudang.', $journal->fresh()->description);
        app()->setLocale('id');
        $this->assertSame($journal->notes, $journal->fresh()->display_notes);
        app()->setLocale('en');
        config(['libretranslate.version' => 'new-model']);
        $this->assertSame($journal->notes, $journal->fresh()->display_notes);
        config(['libretranslate.enabled' => false]);
        $journal->update(['notes' => 'Sumber baru']);
        config(['libretranslate.enabled' => true, 'libretranslate.version' => 'pilot-1']);
        $this->assertSame('Sumber baru', $journal->fresh()->display_notes);
    }

    public function test_both_excel_exports_share_translation_without_network_calls(): void
    {
        $journal = $this->journal();
        Account::create(['account_code' => '111001', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        JournalDetail::create(['journal_id' => $journal->getKey(), 'account_code' => '111001', 'position' => 'DEBET', 'amount' => 125]);
        JournalNoteTranslation::create(['journal_id' => $journal->getKey(), 'locale' => 'en', 'source_hash' => LibreTranslateService::fingerprint($journal->notes), 'translated_text' => 'Rent payment']);
        Http::fake();
        app()->setLocale('en');
        $ledger = (new LedgerExport('111001', '2026-10-01', '2026-10-31'))->array();
        $this->assertContains('Rent payment', array_column($ledger, 2));
        $journals = (new JournalExport('2026-10-01', '2026-10-31'))->array();
        $this->assertSame('Rent payment', $journals[0][3]);
        Http::assertNothingSent();
    }

    public function test_check_command_validates_language_routes_without_notes(): void
    {
        Http::fake([
            '127.0.0.1:5000/health' => Http::response(['status' => 'ok']),
            '127.0.0.1:5000/languages' => Http::response([['code' => 'id', 'targets' => ['en', 'zh']]]),
        ]);
        $this->artisan('translation:journal --check')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_job_uses_dedicated_queue_after_commit_and_missing_record_sends_nothing(): void
    {
        $job = new TranslateJournalNotes('missing');
        $this->assertSame('translations', $job->connection);
        $this->assertSame('translations', $job->queue);
        $this->assertTrue($job->afterCommit);
        $this->assertGreaterThan($job->timeout, config('queue.connections.translations.retry_after'));
        Http::fake();
        config(['libretranslate.enabled' => true]);
        $job->handle(app(LibreTranslateService::class));
        Http::assertNothingSent();
    }

    public function test_partial_failure_preserves_success_and_retry_only_translates_missing_language(): void
    {
        $journal = $this->journal();
        Http::fakeSequence()->push(['translatedText' => 'English'])->push(['translatedText' => ''])->push(['translatedText' => '中文']);
        try {
            app(LibreTranslateService::class)->translateJournal($journal->getKey());
            $this->fail('Empty translation accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Invalid local LibreTranslate response.', $exception->getMessage());
        }
        $this->assertDatabaseCount('journal_note_translations', 1);
        app(LibreTranslateService::class)->translateJournal($journal->getKey());
        $this->assertDatabaseCount('journal_note_translations', 2);
        Http::assertSentCount(3);
    }
}