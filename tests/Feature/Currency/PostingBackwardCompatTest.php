<?php

// FIX: multicurrency — golden IDR membandingkan semua kolom lama dan insert massal lama.
namespace Tests\Feature\Currency;

use App\Models\JournalDetail;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostingBackwardCompatTest extends TestCase
{
    use RefreshDatabase;

    public function test_idr_post_preserves_legacy_fields_and_currency_defaults(): void
    {
        config(['platform.multicurrency_enabled' => false]);
        $header = PostingService::post([
            'journal_id' => 'GOLDEN-IDR', 'transaction_date' => '2026-10-09',
            'journal_type' => 'IMPORT', 'evidence_number' => 'GOLDEN-EVIDENCE',
            'jj_id' => 12345, 'description' => 'Narasi golden',
        ], [
            ['account_code' => '111001', 'position' => 'DEBET', 'amount' => '100.25', 'description' => 'Kas', 'helper_code' => null],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '100.25', 'description' => 'Utang', 'helper_code' => null],
        ]);
        $stored = $header->fresh();
        $this->assertSame('GOLDEN-IDR', $stored->journal_id);
        $this->assertSame('2026-10-09', substr((string) $stored->transaction_date, 0, 10));
        $this->assertSame('GOLDEN-EVIDENCE', $stored->evidence_number);
        $this->assertSame('Narasi golden', $stored->notes);
        $this->assertSame(12345, (int) $stored->jj_id);
        $this->assertSame('IDR', $stored->currency_code);
        $this->assertSame(0, bccomp((string) $stored->exchange_rate, '1', 8));
        $rows = DB::table('journal_details')->where('journal_id', 'GOLDEN-IDR')->orderBy('id')->get();
        foreach ($rows as $index => $row) {
            $this->assertSame('GOLDEN-IDR', $row->journal_id);
            $this->assertSame(['111001', '211001'][$index], $row->account_code);
            $this->assertSame(['DEBET', 'KREDIT'][$index], $row->position);
            $this->assertSame(['Kas', 'Utang'][$index], $row->description);
            $this->assertNull($row->helper_code);
            $this->assertSame(0, bccomp((string) $row->amount, '100.25', 2));
            $this->assertSame('IDR', $row->currency_code);
            $this->assertNull($row->amount_txn);
            $this->assertSame(0, bccomp((string) $row->exchange_rate, '1', 8));
        }
        $this->assertCount(2, $rows);
    }

    public function test_legacy_bulk_insert_still_works_with_no_fx_columns(): void
    {
        DB::table('journal_headers')->insert(['journal_id' => 'LEGACY-BULK']);
        JournalDetail::query()->insert([
            ['journal_id' => 'LEGACY-BULK', 'account_code' => '111001', 'position' => 'DEBET', 'amount' => '12.34'],
            ['journal_id' => 'LEGACY-BULK', 'account_code' => '211001', 'position' => 'KREDIT', 'amount' => '12.34'],
        ]);
        foreach (DB::table('journal_details')->get() as $row) {
            $this->assertSame('IDR', $row->currency_code);
            $this->assertNull($row->amount_txn);
            $this->assertSame(0, bccomp((string) $row->exchange_rate, '1', 8));
        }
    }

    public function test_journal_schema_can_roll_back_and_reapply_without_changing_legacy_amounts(): void
    {
        DB::table('journal_headers')->insert(['journal_id' => 'ROLLBACK-IDR']);
        DB::table('journal_details')->insert([
            'journal_id' => 'ROLLBACK-IDR', 'account_code' => '111001',
            'position' => 'DEBET', 'amount' => '12.34',
        ]);
        $migration = require database_path('migrations/2026_10_11_100001_add_currency_to_journal_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('journal_headers', 'currency_code'));
        $this->assertFalse(Schema::hasColumn('journal_details', 'amount_txn'));
        $this->assertSame(0, bccomp((string) DB::table('journal_details')->value('amount'), '12.34', 2));
        $migration->up();
        $this->assertSame('IDR', DB::table('journal_details')->value('currency_code'));
        $this->assertNull(DB::table('journal_details')->value('amount_txn'));
    }

    public function test_account_schema_rollback_rejects_assigned_currency_without_losing_it(): void
    {
        DB::table('accounts')->insert([
            'account_code' => 'FX-ACCOUNT', 'account_name' => 'Kas USD',
            'coa_type' => 'ASSET', 'normal_balance' => 'DEBET',
            'report_pos' => 'NERACA', 'currency_code' => 'USD',
        ]);
        $migration = require database_path('migrations/2026_10_11_100002_add_currency_to_accounts_table.php');
        try {
            $migration->down();
            $this->fail('Rollback mata uang akun harus ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback ditolak', $exception->getMessage());
        }
        $this->assertSame('USD', DB::table('accounts')->where('account_code', 'FX-ACCOUNT')->value('currency_code'));
        DB::table('accounts')->where('account_code', 'FX-ACCOUNT')->update(['currency_code' => null]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('accounts', 'currency_code'));
        $migration->up();
        $this->assertNull(DB::table('accounts')->where('account_code', 'FX-ACCOUNT')->value('currency_code'));
    }
}