<?php

namespace Tests\Feature;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class LedgerWithoutEvidenceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_renders_a_transaction_without_an_evidence_number(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);

        DB::table('accounts')->insert([
            'account_code' => '111001',
            'account_name' => 'Kas',
            'coa_type' => 'ASSET',
            'normal_balance' => 'DEBET',
            'report_pos' => 'NERACA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $journal = JournalHeader::create([
            'transaction_date' => '2026-09-29',
            'notes' => 'Jurnal buku besar tanpa nomor bukti.',
        ]);
        JournalDetail::create([
            'journal_id' => $journal->journal_id,
            'account_code' => '111001',
            'position' => 'DEBET',
            'amount' => 100000,
        ]);

        $this->get(route('buku-besar.index', [
            'account_code' => '111001',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]))
            ->assertOk()
            ->assertSee($journal->journal_id)
            ->assertSee(route('trace.document', $journal->journal_id));
    }
}
