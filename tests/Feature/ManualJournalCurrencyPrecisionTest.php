<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualJournalCurrencyPrecisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '21100', 'account_name' => 'Hutang', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_manual_journal_accepts_indonesian_amounts_with_two_decimal_places(): void
    {
        $response = $this->post(route('jurnal.store'), $this->payload('1.250,50', '1.250,50'));

        $response->assertRedirect(route('jurnal.index'));
        $this->assertDatabaseHas('journal_details', ['account_code' => '11100', 'amount' => '1250.50']);
        $this->assertDatabaseHas('journal_details', ['account_code' => '21100', 'amount' => '1250.50']);
        $this->assertDatabaseHas('journal_headers', ['evidence_number' => 'GJ-20261001-0001', 'journal_type' => 'MANUAL']);
    }

    public function test_manual_journal_rejects_more_than_two_decimal_places(): void
    {
        $response = $this->from(route('jurnal.create'))->post(route('jurnal.store'), $this->payload('1.250,555', '1.250,555'));

        $response->assertRedirect(route('jurnal.create'));
        $response->assertSessionHasErrors('details.0.amount');
        $this->assertDatabaseCount('journal_headers', 0);
    }

    private function payload(string $debit, string $credit): array
    {
        return [
            'transaction_date' => '2026-10-01',
            'evidence_number' => 'TEST-DESIMAL-001',
            'description' => 'Pengujian nominal dua desimal',
            'details' => [
                ['account_code' => '11100', 'position' => 'DEBET', 'amount' => $debit],
                ['account_code' => '21100', 'position' => 'KREDIT', 'amount' => $credit],
            ],
        ];
    }

    public function test_legacy_schema_allows_edit_and_update_with_currency_flag_off_or_on(): void
    {
        $migration = require database_path('migrations/2026_10_11_100001_add_currency_to_journal_tables.php');
        $migration->down();
        $id = $this->createEditableJournal();

        foreach ([false, true] as $enabled) {
            config(['platform.multicurrency_enabled' => $enabled]);
            $this->get(route('jurnal.edit', $id))->assertOk()->assertViewIs('journal.edit');
            $this->put(route('jurnal.update', $id), $this->payload('1.250,50', '1.250,50'))
                ->assertRedirect(route('jurnal.index'))->assertSessionMissing('error');
            $this->assertDatabaseHas('journal_headers', ['journal_id' => $id, 'notes' => 'Pengujian nominal dua desimal']);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $id, 'amount' => '1250.50']);
        }
    }

    public function test_migrated_base_currency_journal_allows_edit_and_update(): void
    {
        $id = $this->createEditableJournal();
        $this->get(route('jurnal.edit', $id))->assertOk()->assertViewIs('journal.edit');
        $this->put(route('jurnal.update', $id), $this->payload('1.250,50', '1.250,50'))
            ->assertRedirect(route('jurnal.index'))->assertSessionMissing('error');
    }

    public function test_non_unit_exchange_rate_blocks_edit_and_update(): void
    {
        $id = $this->createEditableJournal();
        DB::table('journal_headers')->where('journal_id', $id)->update(['exchange_rate' => '15800']);
        $this->assertForeignJournalIsProtected($id);
    }

    public function test_transaction_snapshot_blocks_edit_and_update_even_at_unit_rate(): void
    {
        $id = $this->createEditableJournal();
        DB::table('journal_details')->where('journal_id', $id)->update(['amount_txn' => '100']);
        $this->assertForeignJournalIsProtected($id);
    }

    private function createEditableJournal(): string
    {
        $id = 'JRN-20261005-000004';
        DB::table('journal_headers')->insert([
            'journal_id' => $id, 'transaction_date' => '2026-10-05',
            'journal_type' => 'MANUAL', 'evidence_number' => 'GJ-20261005-0001', 'notes' => 'Original',
        ]);
        DB::table('journal_details')->insert([
            ['journal_id' => $id, 'account_code' => '11100', 'position' => 'DEBET', 'amount' => '100'],
            ['journal_id' => $id, 'account_code' => '21100', 'position' => 'KREDIT', 'amount' => '100'],
        ]);

        return $id;
    }

    private function assertForeignJournalIsProtected(string $id): void
    {
        $message = 'Jurnal valas tidak dapat diedit; hapus lalu buat ulang.';
        $this->get(route('jurnal.edit', $id))->assertRedirect(route('jurnal.index'))->assertSessionHas('error', $message);
        $this->from(route('jurnal.index'))->put(route('jurnal.update', $id), $this->payload('1.250,50', '1.250,50'))
            ->assertRedirect(route('jurnal.index'))->assertSessionHas('error', $message);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $id, 'notes' => 'Original']);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $id, 'amount' => '100']);
    }
}