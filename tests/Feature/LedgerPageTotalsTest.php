<?php

namespace Tests\Feature;

use App\Exports\LedgerExport;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LedgerPageTotalsTest extends TestCase
{
    use RefreshDatabase;

    public static function balances(): array
    {
        return [
            'debit normal' => ['DEBET', 'DEBET', 1120, 1070],
            'credit normal (contra account)' => ['KREDIT', 'KREDIT', 880, 930],
        ];
    }

    #[DataProvider('balances')]
    public function test_page_footer_shows_mutations_and_last_balance_not_sum_of_balances(
        string $normal, string $openingPosition, int $firstClosing, int $finalClosing
    ): void {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);
        DB::table('accounts')->insert([
            'account_code' => '111001', 'account_name' => 'Test Ledger',
            'coa_type' => 'ASSET', 'normal_balance' => $normal, 'report_pos' => 'NERACA',
        ]);

        foreach ([
            ['2026-09-30', $openingPosition, 1000],
            ['2026-10-20', 'DEBET', 200],
            ['2026-10-21', 'KREDIT', 80],
            ['2026-10-22', 'KREDIT', 50],
        ] as [$date, $position, $amount]) {
            $header = JournalHeader::create(['transaction_date' => $date, 'notes' => 'Test mutation']);
            JournalDetail::create([
                'journal_id' => $header->journal_id, 'account_code' => '111001',
                'position' => $position, 'amount' => $amount,
            ]);
        }

        foreach ([
            [1, 200, 80, $firstClosing],
            [2, 0, 50, $finalClosing],
            [3, 0, 0, $finalClosing],
        ] as [$page, $debit, $credit, $closing]) {
            $response = $this->get(route('buku-besar.index', [
                'account_code' => '111001', 'start_date' => '2026-10-20',
                'end_date' => '2026-10-31', 'per_page' => 2, 'page' => $page,
            ]))->assertOk();

            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $cells = $xpath->query('//tr[@data-ledger-page-summary]/td');
            $this->assertCount(5, $cells);
            $this->assertSame('3', $cells->item(0)->getAttribute('colspan'));
            foreach ([$debit, $credit, $closing] as $index => $value) {
                $this->assertSame(number_format($value, 2, ',', '.'), trim($cells->item($index + 1)->textContent));
            }
        }

        $rows = (new LedgerExport('111001', '2026-10-20', '2026-10-31'))->array();
        $this->assertSame(['200,00', '130,00', number_format($finalClosing, 2, ',', '.')], array_slice(end($rows), 3));
    }

    public function test_empty_period_preserves_opening_balance_in_footer(): void
    {
        View::share('errors', new ViewErrorBag);
        $html = view('ledger.index', [
            'accounts' => collect(), 'accCode' => '111001', 'selectedAccount' => null,
            'startDate' => '2026-10-20', 'endDate' => '2026-10-31',
            'pageOpeningBalance' => -250, 'isDebetNormal' => true, 'perPage' => 50, 'page' => 1,
            'transactions' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
        ])->render();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $cells = (new \DOMXPath($document))->query('//tr[@data-ledger-page-summary]/td');
        $this->assertCount(5, $cells);
        $this->assertSame('0,00', trim($cells->item(1)->textContent));
        $this->assertSame('0,00', trim($cells->item(2)->textContent));
        $this->assertSame('-250,00', trim($cells->item(3)->textContent));
    }
}