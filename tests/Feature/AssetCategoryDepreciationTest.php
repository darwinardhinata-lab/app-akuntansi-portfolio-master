<?php

namespace Tests\Feature;

use App\Imports\AssetImport;
use App\Models\Account;
use App\Models\Asset;
use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssetCategoryDepreciationTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): void
    {
        foreach (['121001' => 'DEBET', '121002' => 'DEBET', '121003' => 'DEBET', '121011' => 'DEBET', '122001' => 'KREDIT', '122002' => 'KREDIT', '630001' => 'DEBET', '510006' => 'DEBET'] as $code => $normal) {
            Account::create(['account_code' => (string) $code, 'account_name' => 'Test '.$code,
                'coa_type' => 'Test', 'normal_balance' => $normal,
                'report_pos' => in_array((string) $code, ['630001', '510006'], true) ? 'LABA RUGI' : 'NERACA']);
        }
    }

    private function asset(string $code, ?string $expense, int $life, string $suffix = ''): Asset
    {
        return Asset::create(['asset_code' => 'AST-'.$code.$suffix, 'asset_name' => 'Test',
            'category' => $code, 'depreciation_expense_code' => $expense,
            'purchase_date' => '2026-10-01', 'purchase_price' => 100, 'residual_value' => 0,
            'useful_life_months' => $life, 'is_active' => true]);
    }

    public function test_category_accounts_are_used_and_land_cip_do_not_depreciate(): void
    {
        $this->accounts();
        $this->asset('121002', '630001', 3);
        $this->asset('121003', '510006', 3);
        $this->asset('121001', null, 0);
        $this->asset('121011', null, 0);
        $journal = app(AssetDepreciationService::class)->post(Carbon::parse('2026-10-02'));
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journal->getKey(), 'account_code' => '122001', 'position' => 'KREDIT', 'amount' => 33.33]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journal->getKey(), 'account_code' => '630001', 'position' => 'DEBET', 'amount' => 33.33]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journal->getKey(), 'account_code' => '510006', 'position' => 'DEBET', 'amount' => 33.33]);
        $this->assertDatabaseCount('journal_details', 4);
        $final = app(AssetDepreciationService::class)->post(Carbon::parse('2026-12-02'));
        $this->assertDatabaseHas('journal_details', ['journal_id' => $final->getKey(), 'account_code' => '122001', 'amount' => 33.34]);
        $this->expectExceptionMessage('sudah diposting');
        app(AssetDepreciationService::class)->post(Carbon::parse('2026-12-03'));
    }

    public function test_land_with_positive_life_is_rejected_without_a_journal(): void
    {
        $this->accounts();
        $this->asset('121001', null, 5);
        try {
            app(AssetDepreciationService::class)->post(Carbon::parse('2026-10-02'));
            $this->fail('Land must not depreciate');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('category', $e->errors());
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }

    public function test_category_form_validates_and_saves_explicit_choice(): void
    {
        $this->withoutMiddleware();
        $this->accounts();
        $asset = $this->asset('121002', null, 0);
        $this->post(route('aset.store'), ['asset_id' => $asset->id, 'category' => '121002',
            'depreciation_expense_code' => '630001', 'useful_life_months' => 36])->assertSessionHas('success');
        $this->assertSame('630001', $asset->fresh()->depreciation_expense_code);
        $this->postJson(route('aset.update', $asset->id), ['category' => '121002',
            'depreciation_expense_code' => '122001', 'useful_life_months' => 36])->assertUnprocessable();
        $this->assertSame('630001', $asset->fresh()->depreciation_expense_code);
    }

    public function test_import_does_not_treat_accumulated_amount_as_useful_life(): void
    {
        $import = new AssetImport;
        $import->collection(new Collection([
            [1, 'OPENING', 'Old asset', 'Building', 1, '2026-10-01', '1000', '300', '700', '0', 'aktif'],
            [2, 'NEW', 'New asset', 'Building', 1, '2026-10-01', '1000', '0', '1000', '0', 'aktif'],
        ]));
        $this->assertDatabaseMissing('assets', ['asset_code' => 'OPENING']);
        $this->assertDatabaseHas('assets', ['asset_code' => 'NEW', 'useful_life_months' => 0, 'category' => null]);
        $this->assertSame(1, $import->getSkippedCount());
    }
}
