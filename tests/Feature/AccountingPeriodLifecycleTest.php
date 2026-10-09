<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.period_lifecycle_preview_enabled' => true]);
    }

    public function test_incomplete_lifecycle_is_fail_closed_by_default(): void
    {
        config(['platform.period_lifecycle_preview_enabled' => false]);
        $actor = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($actor)->postJson(route('accounting-periods.update'), ['month' => '2026-09', 'action' => 'close', 'reason' => 'Rekonsiliasi diperiksa keuangan'])->assertStatus(409);
        $this->assertDatabaseCount('accounting_periods', 0);
    }

    public function test_close_reopen_require_separate_allowlists_and_preserve_events(): void
    {
        $closer = User::factory()->create(['role' => 'FINANCE']);
        $reopener = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.period_close_user_ids' => [$closer->id], 'platform.period_reopen_user_ids' => [$reopener->id]]);
        $data = ['month' => '2026-09', 'action' => 'close', 'reason' => 'Rekonsiliasi bulan telah diperiksa'];
        $this->actingAs($reopener)->postJson(route('accounting-periods.update'), $data)->assertForbidden();
        $this->actingAs($closer)->postJson(route('accounting-periods.update'), $data)->assertOk();
        $this->postJson(route('accounting-periods.update'), $data)->assertOk();
        $this->assertDatabaseCount('accounting_period_events', 1);
        $this->postJson(route('accounting-periods.update'), array_replace($data, ['action' => 'reopen']))->assertForbidden();
        $this->actingAs($reopener)->postJson(route('accounting-periods.update'), array_replace($data, ['action' => 'reopen']))->assertOk();
        $this->assertDatabaseCount('accounting_period_events', 2);
        $this->assertDatabaseHas('accounting_periods', ['month' => '2026-09', 'closed' => false]);
    }

    public function test_unbalanced_journal_blocks_close_without_event_or_partial_record(): void
    {
        $actor = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.period_close_user_ids' => [$actor->id]]);
        $journal = JournalHeader::create(['transaction_date' => '2026-09-15']);
        $journal->details()->create(['account_code' => '111101', 'position' => 'DEBET', 'amount' => 100]);
        $this->actingAs($actor)->postJson(route('accounting-periods.update'), ['month' => '2026-09', 'action' => 'close', 'reason' => 'Rekonsiliasi diperiksa keuangan'])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_period_events', 0);
        $this->assertDatabaseMissing('accounting_periods', ['month' => '2026-09']);
        $journal->details()->create(['account_code' => '211001', 'position' => 'KREDIT', 'amount' => 100]);
        $this->postJson(route('accounting-periods.update'), ['month' => '2026-09', 'action' => 'close', 'reason' => 'Rekonsiliasi diperiksa keuangan'])->assertOk();
        $this->assertDatabaseHas('accounting_periods', ['month' => '2026-09', 'closed' => true]);
    }

    public function test_closed_period_blocks_old_new_dates_details_and_bulk_writes(): void
    {
        $closed = JournalHeader::create(['transaction_date' => '2026-09-15']);
        $detail = $closed->details()->create(['account_code' => '111101', 'position' => 'DEBET', 'amount' => 10]);
        $open = JournalHeader::create(['transaction_date' => '2026-10-15']);
        \Illuminate\Support\Facades\DB::table('accounting_periods')->insert(['month' => '2026-09', 'closed' => true]);
        foreach ([
            fn () => JournalHeader::create(['transaction_date' => '2026-09-30']),
            fn () => $closed->update(['transaction_date' => '2026-10-01']),
            fn () => $open->update(['transaction_date' => '2026-09-01']),
            fn () => $detail->update(['amount' => 20]),
            fn () => $detail->delete(),
            fn () => \App\Models\JournalDetail::insert([['journal_id' => $closed->getKey(), 'account_code' => '111101', 'position' => 'KREDIT', 'amount' => 10]]),
            fn () => \App\Support\ProtectedJournalQuery::table('journal_headers')->where('journal_id', $closed->getKey())->delete(),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Closed period write accepted.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('closed', $e->getMessage());
            }
        }
        $this->assertDatabaseHas('journal_details', ['id' => $detail->id, 'amount' => 10]);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $closed->getKey(), 'transaction_date' => '2026-09-15']);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $open->getKey(), 'transaction_date' => '2026-10-15']);
    }

    public function test_stock_post_and_reversal_reject_closed_original_date_without_mutation(): void
    {
        $product = \App\Models\Product::create(['sku' => 'PERIOD-STOCK', 'name' => 'Period stock', 'stock_quantity' => 0, 'average_cost' => 0]);
        $service = app(\App\Services\InventorySyncService::class);
        $items = [['sku' => $product->sku, 'qty' => 2, 'unit_cost' => 10]];
        $service->processStockMovements($items, 'BIL-PERIOD-STOCK', '2026-09-15', 'BIL');
        \Illuminate\Support\Facades\DB::table('accounting_periods')->insert(['month' => '2026-09', 'closed' => true]);
        foreach ([
            fn () => $service->processStockMovements($items, 'BIL-PERIOD-NEW', '2026-09-16', 'BIL'),
            fn () => $service->reverseStockMovements('BIL-PERIOD-STOCK', 'BIL'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Closed stock transaction accepted.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('closed', $e->getMessage());
            }
        }
        $this->assertEquals(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_ledgers', 1);
    }

    public function test_zero_cost_manual_warehouse_paths_reject_closed_date(): void
    {
        $this->withoutMiddleware();
        $product = \App\Models\Product::create(['sku' => 'WH-PERIOD', 'name' => 'Warehouse', 'stock_quantity' => 10, 'average_cost' => 0]);
        \Illuminate\Support\Facades\DB::table('accounting_periods')->insert(['month' => '2026-09', 'closed' => true]);
        $payload = ['transaction_date' => '2026-09-10', 'offset_account' => '61100', 'evidence_number' => 'WH-CLOSED',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => 0]]];
        foreach (['warehouse.inbound.store', 'warehouse.outbound.store'] as $route) {
            $this->post(route($route), $payload)->assertRedirect()->assertSessionHas('error');
        }
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_ledgers', 0);
        $this->assertDatabaseCount('journal_headers', 0);
    }
}