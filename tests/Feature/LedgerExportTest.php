<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_export_excel_downloads_file(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = Account::create([
            'account_code' => '111001',
            'account_name' => 'Kas Besar',
            'coa_type' => 'ASSET',
            'normal_balance' => 'DEBET',
            'report_pos' => 'NERACA',
        ]);

        $journal = JournalHeader::create([
            'transaction_date' => '2026-10-01',
            'evidence_number' => 'BKM-001',
            'notes' => 'Penerimaan Kas Operasional',
        ]);

        JournalDetail::create([
            'journal_id' => $journal->journal_id,
            'account_code' => '111001',
            'position' => 'DEBET',
            'amount' => 5000000,
        ]);

        // Akses route index buku besar
        $responseIndex = $this->get(route('buku-besar.index', [
            'account_code' => '111001',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $responseIndex->assertOk();
        $responseIndex->assertSee(route('buku-besar.export', [
            'account_code' => '111001',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));
        $responseIndex->assertSee('exportPDF()');

        // Akses route export excel buku besar
        $responseExport = $this->get(route('buku-besar.export', [
            'account_code' => '111001',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $responseExport->assertOk();
        $this->assertStringContainsString('attachment;', $responseExport->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $responseExport->headers->get('content-disposition'));
    }
}
