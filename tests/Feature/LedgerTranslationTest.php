<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\JournalNoteTranslation;
use App\Services\LibreTranslateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_displays_translated_notes_for_active_locale(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        config([
            'libretranslate.enabled' => true,
            'libretranslate.url' => 'http://127.0.0.1:5000',
            'libretranslate.targets' => ['en' => 'en', 'zh_CN' => 'zh-Hans'],
        ]);

        $account = Account::create([
            'account_code' => '111001',
            'account_name' => 'Kas Rupiah Kecil IDR',
            'coa_type' => 'ASSET',
            'normal_balance' => 'DEBET',
            'report_pos' => 'NERACA',
        ]);

        $journal = JournalHeader::create([
            'journal_id' => 'JRN-20261001-000225',
            'transaction_date' => '2026-10-01',
            'notes' => 'Pembelian baterai',
        ]);

        JournalDetail::create([
            'journal_id' => $journal->journal_id,
            'account_code' => '111001',
            'position' => 'KREDIT',
            'amount' => 8000,
        ]);

        $hash = LibreTranslateService::fingerprint($journal->notes);

        JournalNoteTranslation::create([
            'journal_id' => $journal->journal_id,
            'locale' => 'zh_CN',
            'source_hash' => $hash,
            'translated_text' => '电池采购',
        ]);

        JournalNoteTranslation::create([
            'journal_id' => $journal->journal_id,
            'locale' => 'en',
            'source_hash' => $hash,
            'translated_text' => 'Battery purchase',
        ]);

        // Test in Mandarin (zh_CN)
        $this->withSession(['locale' => 'zh_CN'])
            ->get(route('buku-besar.index', [
                'account_code' => '111001',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('电池采购');

        // Test in English (en)
        $this->withSession(['locale' => 'en'])
            ->get(route('buku-besar.index', [
                'account_code' => '111001',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Battery purchase');

        // Test fallback in Indonesian (id)
        $this->withSession(['locale' => 'id'])
            ->get(route('buku-besar.index', [
                'account_code' => '111001',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('Pembelian baterai');
    }

    public function test_translation_command_status(): void
    {
        config(['libretranslate.enabled' => true]);
        $this->artisan('translation:journal --status')->assertSuccessful();
    }

    public function test_translation_command_missing_now(): void
    {
        config([
            'libretranslate.enabled' => true,
            'libretranslate.url' => 'http://127.0.0.1:5000',
            'libretranslate.targets' => ['en' => 'en', 'zh_CN' => 'zh'],
        ]);

        \Illuminate\Support\Facades\Http::fake([
            '127.0.0.1:5000/*' => \Illuminate\Support\Facades\Http::response(['translatedText' => 'Testing sync translation']),
        ]);

        JournalHeader::create([
            'journal_id' => 'JRN-SYNC-TEST-001',
            'transaction_date' => '2026-10-01',
            'notes' => 'Pembelian perlengkapan kantor',
        ]);

        $this->artisan('translation:journal --missing --now')->assertSuccessful();

        $this->assertDatabaseHas('journal_note_translations', [
            'journal_id' => 'JRN-SYNC-TEST-001',
            'locale' => 'en',
            'translated_text' => 'Testing sync translation',
        ]);
    }
}
