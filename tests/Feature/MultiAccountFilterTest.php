<?php

namespace Tests\Feature;

use App\Exports\JournalExport;
use App\Exports\MultiAccountLedgerExport;
use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiAccountFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['libretranslate.enabled' => false]);
        $this->actingAs(User::factory()->create());
        foreach (['111001' => 'Bank One', '111002' => 'Bank Two', '211001' => 'Payable'] as $code => $name) {
            Account::create([
                'account_code' => (string) $code, 'account_name' => $name,
                'coa_type' => 'ASSET', 'normal_balance' => $code === 211001 ? 'KREDIT' : 'DEBET',
                'report_pos' => 'NERACA',
            ]);
        }
    }

    private function entry(string $date, string $notes, array $lines): JournalHeader
    {
        $header = JournalHeader::create(['transaction_date' => $date, 'notes' => $notes]);
        foreach ($lines as [$code, $position, $amount]) {
            JournalDetail::create([
                'journal_id' => $header->journal_id, 'account_code' => $code,
                'position' => $position, 'amount' => $amount,
            ]);
        }

        return $header;
    }

    public function test_ledger_keeps_balances_and_pagination_separate_and_exports_one_sheet_per_account(): void
    {
        $this->entry('2026-09-30', 'Opening', [['111001', 'DEBET', 100], ['111002', 'DEBET', 900]]);
        $this->entry('2026-10-01', 'Movement', [['111001', 'DEBET', 20], ['111002', 'KREDIT', 50]]);
        $this->entry('2026-10-02', 'Second movement', [['111001', 'KREDIT', 10], ['111002', 'DEBET', 80]]);
        $params = [
            'account_codes' => ['111001', '111002'], 'start_date' => '2026-10-01',
            'end_date' => '2026-10-31', 'per_page' => 1, 'page_111001' => 2,
        ];
        $response = $this->get(route('buku-besar.index', $params))->assertOk();
        $sections = $response->viewData('sections');
        $this->assertSame(120.0, $sections[0]['pageOpeningBalance']);
        $this->assertSame(900.0, $sections[1]['pageOpeningBalance']);
        $this->assertSame(2, $sections[0]['transactions']->currentPage());
        $this->assertSame(1, $sections[1]['transactions']->currentPage());
        $this->assertStringContainsString('page_111001=2', $sections[1]['transactions']->nextPageUrl());

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['111001' => '110,00', '111002' => '850,00'] as $code => $closing) {
            $cells = $xpath->query('//div[@data-ledger-account="'.$code.'"]//tr[@data-ledger-page-summary]/td');
            $this->assertCount(5, $cells);
            $this->assertSame($closing, trim($cells->item(3)->textContent));
        }

        $sheets = (new MultiAccountLedgerExport(['111001', '111002'], '2026-10-01', '2026-10-31'))->sheets();
        $this->assertSame(['111001', '111002'], array_map(fn ($sheet) => $sheet->title(), $sheets));
        foreach ([0 => '110,00', 1 => '930,00'] as $index => $closing) {
            $rows = $sheets[$index]->array();
            $this->assertSame($closing, end($rows)[5]);
        }
        $this->get(route('buku-besar.export', $params))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_journal_filters_any_selected_account_without_removing_counterpart_lines(): void
    {
        $one = $this->entry('2026-10-01', 'Selected one', [['111001', 'DEBET', 100], ['211001', 'KREDIT', 100]]);
        $two = $this->entry('2026-10-02', 'Selected two', [['111002', 'DEBET', 200], ['211001', 'KREDIT', 200]]);
        $this->entry('2026-10-03', 'Unselected', [['211001', 'KREDIT', 999]]);
        $params = ['account_codes' => ['111001', '111002'], 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'];
        $response = $this->get(route('jurnal.index', $params))->assertOk();
        $journals = $response->viewData('journals');
        $this->assertEqualsCanonicalizing([$one->journal_id, $two->journal_id], $journals->pluck('journal_id')->all());
        $this->assertSame([2, 2], $journals->pluck('details')->map->count()->all());
        $rows = (new JournalExport('2026-10-01', '2026-10-31', null, ['111001', '111002']))->array();
        $this->assertCount(4, $rows);
        $this->assertSame(2, collect($rows)->filter(fn ($row) => str_starts_with($row[8], '211001'))->count());
        $this->get(route('jurnal.export', $params))->assertOk();
        $this->assertSame(1, $this->get(route('jurnal.index', $params + ['search' => 'Selected one']))
            ->assertOk()->viewData('journals')->total());
        $this->assertSame(3, $this->get(route('jurnal.index'))->assertOk()->viewData('journals')->total());
    }

    public function test_filters_reject_unknown_accounts_and_non_array_multi_selection(): void
    {
        foreach (['buku-besar.index', 'buku-besar.export', 'jurnal.index', 'jurnal.export'] as $route) {
            $this->getJson(route($route, ['account_codes' => ['does-not-exist']]))->assertUnprocessable();
            $this->getJson(route($route, ['account_codes' => '111001']))->assertUnprocessable();
        }
    }
}