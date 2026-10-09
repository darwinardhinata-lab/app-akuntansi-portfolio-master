<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\PurchaseReturnValuationService;
use App\Support\JournalBalanceValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseReturnValuationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): void
    {
        config(['coa.selisih_retur_pembelian' => '619999']);
        foreach ([config('coa.hutang_usaha'), config('coa.persediaan'), '619999'] as $code) {
            DB::table('accounts')->updateOrInsert(['account_code' => $code], [
                'account_name' => 'Test', 'normal_balance' => 'DEBET',
                'coa_type' => 'EXPENSE', 'report_pos' => 'NERACA',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_variance_both_directions_and_equal_cost_are_balanced(): void
    {
        $this->accounts();
        foreach ([300, 100, 200, 0] as $carrying) {
            $lines = app(PurchaseReturnValuationService::class)->journalDetails('JRN-TEST', config('coa.hutang_usaha'), 200, $carrying);
            $this->assertTrue(JournalBalanceValidator::isBalanced($lines));
            $inventory = collect($lines)->where('account_code', config('coa.persediaan'))->sum('amount');
            $this->assertEquals($carrying, $inventory);
            $variance = collect($lines)->firstWhere('account_code', '619999');
            if ($carrying === 200) {
                $this->assertNull($variance);
            } else {
                $this->assertEquals(abs($carrying - 200), $variance['amount']);
                $this->assertSame($carrying > 200 ? 'DEBET' : 'KREDIT', $variance['position']);
            }
        }
    }

    private function receipt(): array
    {
        $product = Product::create(['sku' => 'RETURN-VALUATION', 'name' => 'Test', 'stock_quantity' => 10, 'average_cost' => 150]);
        $po = DB::table('purchase_orders')->insertGetId([
            'po_number' => 'PO-VALUATION', 'transaction_date' => '2026-10-06',
            'contact_name' => 'Vendor', 'status' => 'RECEIVED', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detail = DB::table('purchase_order_details')->insertGetId([
            'purchase_order_id' => $po, 'product_id' => $product->id, 'item_code' => $product->sku,
            'description' => 'Test', 'price' => 100, 'qty' => 10, 'qty_received' => 10, 'amount' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$product, ['purchase_order_id' => $po, 'return_date' => '2026-10-06', 'items' => [$detail => 2]]];
    }

    public function test_posted_inventory_credit_matches_ledger_carrying_value(): void
    {
        $this->withoutMiddleware();
        $this->accounts();
        [$product, $payload] = $this->receipt();
        $this->post(route('purchase-returns.store'), $payload)->assertSessionHas('success');
        $this->assertEquals(8, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('journal_details', ['account_code' => config('coa.persediaan'), 'position' => 'KREDIT', 'amount' => 300]);
        $this->assertDatabaseHas('journal_details', ['account_code' => '619999', 'position' => 'DEBET', 'amount' => 100]);
        $this->assertDatabaseHas('inventory_ledgers', ['product_id' => $product->id, 'type' => 'OUT', 'total_cost' => 300]);
    }

    public function test_missing_variance_account_rolls_back_all_effects(): void
    {
        $this->withoutMiddleware();
        $this->accounts();
        config(['coa.selisih_retur_pembelian' => null]);
        [$product, $payload] = $this->receipt();
        $this->post(route('purchase-returns.store'), $payload)->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        foreach (['purchase_returns', 'purchase_return_details', 'inventory_ledgers', 'journal_headers', 'journal_details'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}