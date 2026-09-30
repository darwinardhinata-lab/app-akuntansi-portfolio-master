<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalMassDeleteRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_mass_delete_route_is_not_captured_by_the_single_journal_delete_route(): void
    {
        $this->withoutMiddleware();

        $journal = JournalHeader::create([
            'transaction_date' => '2026-09-29',
            'evidence_number' => 'TEST-MASS-DELETE-001',
            'notes' => 'Journal mass-delete route test',
        ]);

        $response = $this->delete(route('jurnal.massDestroy'), [
            'ids' => [$journal->journal_id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 transaksi jurnal berhasil dihapus secara massal!');
        $this->assertDatabaseMissing('journal_headers', ['journal_id' => $journal->journal_id]);
    }
}