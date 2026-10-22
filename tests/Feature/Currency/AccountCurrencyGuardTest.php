<?php

namespace Tests\Feature\Currency;

use App\Models\JournalDetail;
use App\Modules\Platform\Models\Company;
use App\Support\ProtectedJournalQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AccountCurrencyGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.multicurrency_enabled' => true]);
        Company::query()->create(['code' => 'ABC', 'name' => 'Test', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['USD-BANK' => ['Cash & Bank', 'USD'], 'BASE-BANK' => ['Cash & Bank', null], 'EQUITY' => ['EQUITY', null]] as $code => [$type, $currency]) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $code,
                'coa_type' => $type, 'currency_code' => $currency, 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        }
        DB::table('journal_headers')->insert(['journal_id' => 'GUARD', 'transaction_date' => '2026-10-09']);
    }

    private function row(array $changes = []): array
    {
        return array_merge(['journal_id' => 'GUARD', 'account_code' => 'USD-BANK', 'position' => 'DEBET',
            'amount' => '15800', 'currency_code' => 'USD', 'amount_txn' => '1', 'exchange_rate' => '15800'], $changes);
    }

    public function test_all_insert_entry_points_reject_mismatched_currency(): void
    {
        $invalid = $this->row(['currency_code' => 'IDR', 'amount_txn' => null]);
        $writers = [
            fn () => JournalDetail::query()->create($invalid),
            fn () => JournalDetail::query()->insert([$invalid]),
            fn () => ProtectedJournalQuery::table('journal_details')->insert([$invalid]),
            fn () => ProtectedJournalQuery::table('journal_details')->insertOrIgnore([$invalid]),
            fn () => ProtectedJournalQuery::table('journal_details')->insertGetId($invalid),
            fn () => \App\Models\JournalHeader::query()->findOrFail('GUARD')->details()->createMany([$invalid]),
        ];
        foreach ($writers as $write) {
            try { $write(); $this->fail('Baris salah mata uang harus ditolak.'); }
            catch (RuntimeException $e) { $this->assertStringContainsString('USD-BANK', $e->getMessage()); }
        }
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_base_bank_rejects_fx_and_invalid_batch_is_atomic(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            ProtectedJournalQuery::table('journal_details')->insert([$this->row(), $this->row(['account_code' => 'BASE-BANK'])]);
        } finally { $this->assertDatabaseCount('journal_details', 0); }
    }

    public function test_valid_foreign_base_and_counterpart_rows_are_accepted(): void
    {
        ProtectedJournalQuery::table('journal_details')->insert([$this->row(), $this->row(['account_code' => 'EQUITY']),
            $this->row(['account_code' => 'BASE-BANK', 'currency_code' => 'IDR', 'amount_txn' => null, 'exchange_rate' => '1'])]);
        $this->assertDatabaseCount('journal_details', 3);
    }

    public function test_update_merges_snapshot_and_rejection_preserves_rows(): void
    {
        $id = ProtectedJournalQuery::table('journal_details')->insertGetId($this->row());
        $before = DB::table('journal_details')->get()->toJson();
        foreach ([['amount_txn' => null], ['account_code' => 'BASE-BANK'], ['currency_code' => DB::raw("'IDR'")]] as $changes) {
            try { ProtectedJournalQuery::table('journal_details')->where('id', $id)->update($changes); $this->fail('Update harus ditolak.'); }
            catch (RuntimeException $e) { $this->assertNotSame('', $e->getMessage()); }
            $this->assertSame($before, DB::table('journal_details')->get()->toJson());
        }
        JournalDetail::query()->whereKey($id)->update(['description' => 'metadata']);
        $this->assertDatabaseHas('journal_details', ['id' => $id, 'description' => 'metadata']);
    }

    public function test_flag_off_retains_legacy_inserts_without_company_lookup(): void
    {
        config(['platform.multicurrency_enabled' => false]);
        Company::query()->delete();
        ProtectedJournalQuery::table('journal_details')->insert($this->row(['currency_code' => 'IDR', 'amount_txn' => null]));
        $this->assertDatabaseCount('journal_details', 1);
    }

    public function test_schema_defaults_are_idr_not_assumed_company_base(): void
    {
        Company::query()->update(['base_currency' => 'CNY']);
        $row = $this->row(['account_code' => 'BASE-BANK']);
        unset($row['currency_code'], $row['amount_txn'], $row['exchange_rate']);
        try { ProtectedJournalQuery::table('journal_details')->insert($row); $this->fail('Default IDR bukan CNY.'); }
        catch (RuntimeException $e) { $this->assertStringContainsString('BASE-BANK', $e->getMessage()); }
        ProtectedJournalQuery::table('journal_details')->insert(array_merge($row, ['currency_code' => 'CNY']));
        $this->assertDatabaseCount('journal_details', 1);
    }
}