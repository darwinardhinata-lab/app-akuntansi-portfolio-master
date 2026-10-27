<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class JournalDescriptionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_journal_description_is_saved_to_notes_and_displayed(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);

        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '21100', 'account_name' => 'Hutang', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $description = 'Pembelian perlengkapan kantor September';

        $response = $this->post(route('jurnal.store'), [
            'transaction_date' => '2026-09-29',
            'source_doc_no' => 'TEST-DESC-001',
            'description' => $description,
            'details' => [
                ['account_code' => '11100', 'position' => 'DEBET', 'amount' => 100000],
                ['account_code' => '21100', 'position' => 'KREDIT', 'amount' => 100000],
            ],
        ]);

        $response->assertRedirect(route('jurnal.index'));
        $this->assertDatabaseHas('journal_headers', ['evidence_number' => 'GJ-20260929-0001', 'source_doc_no' => 'TEST-DESC-001', 'notes' => $description]);

        $journal = JournalHeader::where('source_doc_no', 'TEST-DESC-001')->firstOrFail();
        $this->assertSame($description, $journal->description);
        $this->get(route('jurnal.index'))->assertOk()->assertSee($description);
    }

    public function test_consolidated_manual_posting_keeps_fixed_asset_link_to_persisted_detail(): void
    {
        $this->withoutMiddleware();
        config(['coa.aset_tetap' => '12000']);
        foreach (['12000' => 'DEBET', '21100' => 'KREDIT'] as $code => $normal) {
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => 'Anonymous '.$code,
                'coa_type' => 'ASSET', 'normal_balance' => $normal, 'report_pos' => 'NERACA',
            ]);
        }
        $this->post(route('jurnal.store'), [
            'transaction_date' => '2026-09-29', 'source_doc_no' => 'ASSET-BASELINE',
            'description' => 'Anonymous fixed asset', 'details' => [
                ['account_code' => '12000', 'position' => 'DEBET', 'amount' => '125,50'],
                ['account_code' => '21100', 'position' => 'KREDIT', 'amount' => '125,50'],
            ],
        ])->assertRedirect(route('jurnal.index'))->assertSessionMissing('error');

        $detail = DB::table('journal_details')->where('account_code', '12000')->first();
        $this->assertNotNull($detail);
        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseHas('assets', [
            'journal_detail_id' => $detail->id, 'asset_name' => 'Anonymous fixed asset',
            'purchase_price' => '125.50',
        ]);
        $asset = \App\Models\Asset::where('journal_detail_id', $detail->id)->firstOrFail();
        $this->assertSame('2026-09-29', $asset->purchase_date->format('Y-m-d'));
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
    }
}
