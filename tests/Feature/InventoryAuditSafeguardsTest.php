<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use App\Models\Product;
use App\Services\InventorySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAuditSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
    }

    private function product(float $stock = 10, float $cost = 100): Product
    {
        return Product::create(['sku' => 'AUDIT-STOCK', 'name' => 'Audit', 'stock_quantity' => $stock, 'average_cost' => $cost]);
    }

    public function test_cumulative_outbound_is_rejected_without_partial_writes(): void
    {
        $product = $this->product(2);
        foreach (['INV', 'PR'] as $type) {
            try {
                app(InventorySyncService::class)->processStockMovements([
                    ['sku' => $product->sku, 'qty' => 1], ['sku' => $product->sku, 'qty' => 2],
                ], 'OVER-' . $type, '2026-10-06', $type);
                $this->fail('Insufficient stock was accepted');
            } catch (\Exception $e) {
                $this->assertSame(__('erp.audit_stock_insufficient', ['sku' => $product->sku]), $e->getMessage());
            }
            $this->assertEquals(2, $product->fresh()->stock_quantity);
            $this->assertDatabaseCount('inventory_ledgers', 0);
        }
    }

    public function test_bill_reversal_restores_value_and_retains_history_idempotently(): void
    {
        $product = $this->product();
        $service = app(InventorySyncService::class);
        $service->processStockMovements([['sku' => $product->sku, 'qty' => 10, 'unit_cost' => 200]], 'BIL-AUDIT', '2026-10-06', 'BIL');
        $this->assertEquals(150, $product->fresh()->average_cost);
        $service->reverseStockMovements('BIL-AUDIT', 'BIL');
        $service->reverseStockMovements('BIL-AUDIT', 'BIL');
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(100, $product->fresh()->average_cost);
        $this->assertDatabaseHas('inventory_ledgers', ['evidence_number' => 'BIL-AUDIT', 'type' => 'IN']);
        $this->assertDatabaseCount('inventory_ledgers', 2);
    }

    public function test_reversal_rejects_downstream_usage_and_preserves_stock(): void
    {
        $product = $this->product(0, 0);
        $service = app(InventorySyncService::class);
        $service->processStockMovements([['sku' => $product->sku, 'qty' => 10, 'unit_cost' => 200]], 'BIL-AUDIT', '2026-10-06', 'BIL');
        $service->processStockMovements([['sku' => $product->sku, 'qty' => 8]], 'INV-AUDIT', '2026-10-06', 'INV');
        try {
            $service->reverseStockMovements('BIL-AUDIT', 'BIL');
            $this->fail('Downstream usage was accepted');
        } catch (\Exception $e) {
            $this->assertSame(__('erp.audit_stock_downstream'), $e->getMessage());
        }
        $this->assertEquals(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_ledgers', 2);
    }

    public function test_master_update_cannot_overwrite_stock(): void
    {
        $product = $this->product();
        try {
            app(ProductController::class)->update(Request::create('/', 'PUT', [
                'sku' => $product->sku, 'name' => 'Changed', 'sell_price' => 1, 'stock_quantity' => 100,
            ]), $product->id);
            $this->fail('Master stock was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock_quantity', $e->errors());
        }
        $this->assertEquals(10, $product->fresh()->stock_quantity);
    }

    public function test_zero_cost_manual_movements_still_write_ledgers(): void
    {
        $product = $this->product(0, 0);
        $controller = app(WarehouseController::class);
        $data = ['transaction_date' => '2026-10-06', 'offset_account' => 'TEST',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => 0]]];
        $controller->storeInbound(Request::create('/', 'POST', $data + ['evidence_number' => 'ZERO-IN']));
        $this->assertEquals(1, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_ledgers', ['evidence_number' => 'ZERO-IN', 'qty' => 1, 'total_cost' => 0]);
        $controller->storeOutbound(Request::create('/', 'POST', $data + ['evidence_number' => 'ZERO-OUT']));
        $this->assertEquals(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_ledgers', 2);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_negative_manual_cost_is_rejected(): void
    {
        $product = $this->product();
        $this->expectException(ValidationException::class);
        app(WarehouseController::class)->storeInbound(Request::create('/', 'POST', [
            'evidence_number' => 'NEGATIVE', 'transaction_date' => '2026-10-06', 'offset_account' => 'TEST',
            'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => -1]],
        ]));
    }

    public function test_reversing_later_receipt_allows_earlier_receipt_reversal(): void
    {
        $product = $this->product();
        $service = app(InventorySyncService::class);
        foreach (['BIL-FIRST', 'BIL-SECOND'] as $number) {
            $service->processStockMovements([['sku' => $product->sku, 'qty' => 10, 'unit_cost' => 200]], $number, '2026-10-06', 'BIL');
        }
        $service->reverseStockMovements('BIL-SECOND', 'BIL');
        $service->reverseStockMovements('BIL-FIRST', 'BIL');
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(100, $product->fresh()->average_cost);
        $this->assertDatabaseCount('inventory_ledgers', 4);
    }

    public function test_master_csv_import_preserves_stock_and_new_products_start_at_zero(): void
    {
        $product = $this->product();
        $rows = [implode(';', ['Item Group', 'Group Description', 'Item Name', 'Item Code',
            'Category', 'Keterangan/Varian', 'Merek', 'Ukuran', 'Berat', 'Panjang', 'Lebar',
            'Sell Price', 'Purchase Price', 'Barcode', 'Pajak', 'Minimum Stock', 'Maximum Stock', 'Stock'])];
        foreach ([$product->sku, 'NEW-AUDIT'] as $sku) {
            $row = array_fill(0, 18, '');
            $row[2] = 'Imported'; $row[3] = $sku; $row[11] = '10'; $row[17] = '100';
            $rows[] = implode(';', $row);
        }
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', implode("\n", $rows));
        $request = Request::create('/', 'POST', [], [], ['file_csv' => $file]);
        app(ProductController::class)->import($request);
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(100, $product->fresh()->average_cost);
        $this->assertDatabaseHas('products', ['sku' => 'NEW-AUDIT', 'stock_quantity' => 0]);
        $this->assertDatabaseCount('inventory_ledgers', 0);
    }

    public function test_fractional_core_quantity_is_rejected_instead_of_truncated(): void
    {
        $product = $this->product();
        $this->expectExceptionMessage(__('erp.audit_stock_invalid_input'));
        app(InventorySyncService::class)->processStockMovements([
            ['sku' => $product->sku, 'qty' => 0.5],
        ], 'FRACTION', '2026-10-06', 'INV');
    }

    public function test_reversal_uses_ledger_value_not_rounded_average_cost(): void
    {
        $product = $this->product(1, 100);
        $service = app(InventorySyncService::class);
        $service->processStockMovements([['sku' => $product->sku, 'qty' => 2, 'unit_cost' => 101]], 'BIL-ROUND', '2026-10-06', 'BIL');
        // Simulate database DECIMAL(20,2) storage on SQLite, which does not enforce scale.
        $product->refresh()->update(['average_cost' => 100.67]);
        $service->reverseStockMovements('BIL-ROUND', 'BIL');
        $this->assertEquals(1, $product->fresh()->stock_quantity);
        $this->assertEquals(100, $product->fresh()->average_cost);
    }
}