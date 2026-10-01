<?php

namespace Tests\Feature;

use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class JournalDocumentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);

        DB::table('accounts')->insert([
            ['account_code' => '11100', 'account_name' => 'Kas', 'coa_type' => 'ASSET', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
            ['account_code' => '21100', 'account_name' => 'Hutang', 'coa_type' => 'LIABILITY', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_manual_and_import_journals_link_to_manual_journal_edit_page(): void
    {
        $manual = $this->journal('GJ-MANUAL-001', 'MANUAL');
        $import = $this->journal('INV-IMPORTED-001', 'IMPORT');

        $this->get(route('jurnal.index'))
            ->assertOk()
            ->assertSee(route('jurnal.edit', $manual->journal_id))
            ->assertSee(route('jurnal.edit', $import->journal_id));

        $this->get(route('buku-besar.index', $this->ledgerParameters()))
            ->assertOk()
            ->assertSee(route('jurnal.edit', $manual->journal_id))
            ->assertSee(route('jurnal.edit', $import->journal_id));
    }

    public function test_operational_journal_links_to_the_source_invoice_and_keeps_journal_link_in_modal(): void
    {
        $journal = $this->journal('INV-001', null);
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-001',
            'transaction_date' => '2026-10-01',
            'contact_name' => 'Pelanggan Test',
            'grand_total' => 100,
            'journal_id' => $journal->journal_id,
        ]);

        $this->get(route('jurnal.index'))
            ->assertOk()
            ->assertSee(route('invoice.show', $invoice->id))
            ->assertSee(route('jurnal.edit', $journal->journal_id));

        $this->get(route('buku-besar.index', $this->ledgerParameters()))
            ->assertOk()
            ->assertSee(route('invoice.show', $invoice->id))
            ->assertSee(route('jurnal.edit', $journal->journal_id));
    }

    public function test_journal_detail_popup_is_scoped_to_the_requested_journal_id(): void
    {
        $first = $this->journal('GJ-SAME-EVIDENCE', 'IMPORT', 100);
        $second = $this->journal('GJ-SAME-EVIDENCE', 'IMPORT', 200);

        $this->getJson(route('jurnal.detail.ajax', ['journal_id' => $first->journal_id]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('html', fn (string $html) => str_contains($html, '100,00') && ! str_contains($html, '200,00'));

        $this->assertNotSame($first->journal_id, $second->journal_id);
    }

    private function journal(string $evidence, ?string $type, int $amount = 100): JournalHeader
    {
        $journal = JournalHeader::create([
            'transaction_date' => '2026-10-01',
            'evidence_number' => $evidence,
            'notes' => 'Jurnal pengujian hyperlink',
            'journal_type' => $type,
        ]);

        JournalDetail::insert([
            ['journal_id' => $journal->journal_id, 'account_code' => '11100', 'position' => 'DEBET', 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()],
            ['journal_id' => $journal->journal_id, 'account_code' => '21100', 'position' => 'KREDIT', 'amount' => $amount, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $journal;
    }

    private function ledgerParameters(): array
    {
        return ['account_code' => '11100', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01'];
    }
}