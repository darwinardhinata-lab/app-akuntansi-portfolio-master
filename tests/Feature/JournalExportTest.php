<?php

namespace Tests\Feature;

use App\Exports\JournalExport;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_matches_import_template_columns_and_row_format(): void
    {
        $journal = $this->createJournal('2026-10-01', 'Owner capital contribution');
        $journal->update(['journal_no' => 'GJ-1424034', 'source_doc_no' => 'CAPITAL-001']);
        JournalDetail::create([
            'journal_id' => $journal->journal_id,
            'account_code' => '111001',
            'position' => 'KREDIT',
            'amount' => 1000000,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('jurnal.download-template'));
        $response->assertOk();
        $header = explode("\n", $response->streamedContent())[0];
        $templateHeadings = str_getcsv(ltrim($header, "\xEF\xBB\xBF"));
        $export = new JournalExport();

        $this->assertSame($templateHeadings, $export->headings());
        $this->assertSame([
            ['01 Oct 2026', 'GJ-1424034', 'CAPITAL-001', 'Owner capital contribution',
                '1.000.000,00', '1.000.000,00', '1.000.000,00', '0,00', '111001 - Cash account'],
            ['01 Oct 2026', 'GJ-1424034', 'CAPITAL-001', 'Owner capital contribution',
                '1.000.000,00', '1.000.000,00', '0,00', '1.000.000,00', '111001 - Cash account'],
        ], $export->array());
    }

    public function test_export_searches_notes_evidence_account_code_and_account_name(): void
    {
        $journal = $this->createJournal('2026-10-01', 'Owner capital contribution');
        $this->createJournal('2026-10-02', 'Unrelated transaction', '222002', 'Other account');

        foreach (['owner', $journal->evidence_number, '111001', 'Cash account'] as $search) {
            $rows = (new JournalExport(null, null, $search))->array();

            $this->assertCount(1, $rows, $search);
            $this->assertSame($journal->evidence_number, $rows[0][1]);
            $this->assertSame('Owner capital contribution', $rows[0][3]);
            $this->assertSame('111001 - Cash account', $rows[0][8]);
        }

        $this->assertSame([], (new JournalExport(null, null, 'no-match'))->array());
    }

    public function test_export_keeps_date_filters_when_searching(): void
    {
        $this->createJournal('2026-09-30', 'Owner before period');
        $first = $this->createJournal('2026-10-01', 'Owner first day');
        $last = $this->createJournal('2026-10-31', 'Owner last day');
        $this->createJournal('2026-11-01', 'Owner after period');

        $rows = (new JournalExport('2026-10-01', '2026-10-31', 'owner'))->array();

        $this->assertCount(2, $rows);
        $this->assertSame($last->evidence_number, $rows[0][1]);
        $this->assertSame($first->evidence_number, $rows[1][1]);
        $this->assertCount(4, (new JournalExport())->array());
    }

    public function test_filtered_export_downloads_an_excel_file(): void
    {
        $this->createJournal('2026-10-01', 'Owner capital contribution');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('jurnal.export', ['search' => 'owner']));

        $response->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    private function createJournal(
        string $date,
        string $notes,
        string $accountCode = '111001',
        string $accountName = 'Cash account'
    ): JournalHeader {
        Account::firstOrCreate(['account_code' => $accountCode], [
            'account_name' => $accountName,
            'coa_type' => 'ASSET',
            'normal_balance' => 'DEBET',
            'report_pos' => 'NERACA',
        ]);

        $journal = JournalHeader::create([
            'transaction_date' => $date,
            'notes' => $notes,
            'journal_type' => 'MANUAL',
        ]);

        JournalDetail::create([
            'journal_id' => $journal->journal_id,
            'account_code' => $accountCode,
            'position' => 'DEBET',
            'amount' => 1000000,
        ]);

        return $journal;
    }
}