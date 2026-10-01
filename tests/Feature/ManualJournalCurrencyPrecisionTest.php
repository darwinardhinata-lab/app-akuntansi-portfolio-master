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
}