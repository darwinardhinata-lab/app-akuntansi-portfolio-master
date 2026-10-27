<?php

namespace Tests\Feature;

use App\Exports\LedgerExport;
use App\Models\JournalHeader;
use App\Support\JournalAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegrationMatrixBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Do not permit this baseline to run against an operational database.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->withoutMiddleware();
        foreach ([['111001', 'ASSET', 'DEBET'], ['211001', 'LIABILITY', 'KREDIT'], ['611001', 'EXPENSE', 'DEBET']] as [$code, $type, $normal]) {
            DB::table('accounts')->insert([
                'account_code' => $code, 'account_name' => 'Anonymous '.$code,
                'coa_type' => $type, 'normal_balance' => $normal, 'report_pos' => 'NERACA',
            ]);
        }
        DB::table('helper_codes')->insert([
            'helper_code' => 'BASELINE-PARTY', 'entity_name' => 'Anonymous party', 'normal_balance' => 'KREDIT',
        ]);
    }

    public static function matrixCases(): array
    {
        $fixtures = require __DIR__.'/../Fixtures/integration/matrix-baseline.php';

        return array_map(fn (array $lines) => [$lines], $fixtures);
    }

    #[DataProvider('matrixCases')]
    public function test_manual_input_preserves_every_line_and_source_reference(array $lines): void
    {
        $this->post(route('jurnal.store'), $this->payload($lines))
            ->assertRedirect(route('jurnal.index'))->assertSessionMissing('error');
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', count($lines));
        $header = JournalHeader::firstOrFail();
        $this->assertSame('ANON-BASELINE', $header->source_doc_no);
        $this->assertSame('Anonymous baseline', $header->notes);
        $this->assertSame('GJ-20261001-0001', $header->evidence_number);
        foreach ($lines as $line) {
            $this->assertDatabaseHas('journal_details', [
                'journal_id' => $header->getKey(), 'account_code' => $line['account_code'],
                'helper_code' => $line['helper_code'] ?? null, 'position' => $line['position'],
                'amount' => JournalAmount::normalize($line['amount']),
            ]);
        }
    }

    public function test_offsetting_manual_fixture_preserves_original_and_nets_credit_normal_ledger_to_zero(): void
    {
        $fixtures = require __DIR__.'/../Fixtures/integration/matrix-baseline.php';
        $lines = $fixtures['two-line'];
        $this->post(route('jurnal.store'), $this->payload($lines))->assertSessionMissing('error');
        $original = JournalHeader::firstOrFail();
        $offset = array_map(function (array $line) {
            $line['position'] = $line['position'] === 'DEBET' ? 'KREDIT' : 'DEBET';

            return $line;
        }, $lines);
        $payload = $this->payload($offset);
        $payload['transaction_date'] = '2026-10-02';
        $payload['source_doc_no'] = 'ANON-OFFSET';
        $this->post(route('jurnal.store'), $payload)->assertSessionMissing('error');
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('journal_details', 4);
        $this->assertSame('ANON-BASELINE', $original->fresh()->source_doc_no);
        $rows = (new LedgerExport('211001', '2026-10-01', '2026-10-31'))->array();
        $this->assertSame(['1.250,50', '1.250,50', '0,00'], array_slice(end($rows), 3));
        // This is a manual offset fixture, NOT proof of a linked reversal workflow.
    }

    private function payload(array $lines): array
    {
        return [
            'transaction_date' => '2026-10-01', 'source_doc_no' => 'ANON-BASELINE',
            'description' => 'Anonymous baseline', 'details' => $lines,
        ];
    }
}