<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class JournalIndexWithoutEvidenceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_index_renders_a_journal_without_an_evidence_number(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);

        $journal = JournalHeader::create([
            'transaction_date' => '2026-09-29',
            'notes' => 'Jurnal tanpa nomor bukti.',
        ]);

        $this->get(route('jurnal.index'))
            ->assertOk()
            ->assertSee($journal->journal_id)
            ->assertSee(route('trace.document', $journal->journal_id));
    }
}