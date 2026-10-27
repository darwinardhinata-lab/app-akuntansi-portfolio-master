<?php

namespace Tests\Feature;

use App\Exports\LedgerExport;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationPeriodBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_and_monthly_movements_carry_forward_without_duplication(): void
    {
        foreach (['111099' => 'DEBET', '311099' => 'KREDIT'] as $code => $normal) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Anonymous '.$code,
                'coa_type' => 'Other', 'normal_balance' => $normal, 'report_pos' => 'NERACA']);
        }
        foreach ([['2025-12-31', '100.25', true], ['2026-01-15', '20.10', false], ['2026-02-15', '30.20', false]] as [$date, $amount, $opening]) {
            PostingService::post(['transaction_date' => $date, 'is_opening_balance' => $opening,
                'source_doc_no' => 'BASELINE-'.$date], [
                ['account_code' => '111099', 'position' => 'DEBET', 'amount' => $amount],
                ['account_code' => '311099', 'position' => 'KREDIT', 'amount' => $amount],
            ]);
        }
        foreach (['111099', '311099'] as $code) {
            $jan = (new LedgerExport($code, '2026-01-01', '2026-01-31'))->array();
            $feb = (new LedgerExport($code, '2026-02-01', '2026-02-28'))->array();
            $year = (new LedgerExport($code, '2026-01-01', '2026-12-31'))->array();
            $this->assertCount(3, $jan);
            $this->assertCount(3, $feb);
            $this->assertCount(4, $year);
            $this->assertSame('100,25', $jan[0][5]);
            $this->assertSame('120,35', end($jan)[5]);
            $this->assertSame('120,35', $feb[0][5]);
            $this->assertSame('150,55', end($feb)[5]);
            $this->assertSame('150,55', end($year)[5]);
        }
        $this->assertDatabaseCount('journal_headers', 3);
        $this->assertDatabaseCount('journal_details', 6);
        $this->assertSame(0, bccomp((string) DB::table('journal_details')->where('position', 'DEBET')->sum('amount'),
            (string) DB::table('journal_details')->where('position', 'KREDIT')->sum('amount'), 2));
    }
}