<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FIX: Regresi bug voidReceipt() — pencocokan LIKE berbasis prefix membuat void PO-1
 * ikut menghapus jurnal, kartu stok, dan mengoreksi stok milik PO-10.
 */
class PurchaseOrderVoidReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
    }

    public function test_void_receipt_tidak_menyentuh_po_dengan_prefix_nomor_yang_sama(): void
    {
        $po1  = $this->receivePo('PO-1',  'SKU-V1',  'BIL-VOID-1',  5);
        $po10 = $this->receivePo('PO-10', 'SKU-V10', 'BIL-VOID-10', 7);

        app(PurchaseOrderService::class)->voidReceipt($po1);

        // Periksa collision prefix lebih dahulu: patch lama memilih PO-10 saat void PO-1.
        $this->assertDatabaseHas('journal_headers', ['source_doc_no' => 'BIL-VOID-10', 'transaction_type' => 'Purchase Bill']);
        $this->assertDatabaseHas('inventory_ledgers', ['evidence_number' => 'BIL-VOID-10']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po10, 'status' => 'RECEIVED']);
        $this->assertDatabaseHas('purchase_order_details', ['purchase_order_id' => $po10, 'qty_received' => 7]);
        $this->assertEquals(7, (float) Product::where('sku', 'SKU-V10')->value('stock_quantity'));
        $this->assertNotNull(DB::table('purchase_bills')->where('bill_number', 'BIL-VOID-10')->value('journal_id'));

        // PO-1: semua efek receive terhapus dan tautan Bill dilepas.
        $this->assertDatabaseMissing('journal_headers', ['source_doc_no' => 'BIL-VOID-1']);
        $this->assertDatabaseMissing('inventory_ledgers', ['evidence_number' => 'BIL-VOID-1']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $po1, 'status' => 'APPROVED']);
        $this->assertEquals(0, (float) Product::where('sku', 'SKU-V1')->value('stock_quantity'));
        $this->assertNull(DB::table('purchase_bills')->where('bill_number', 'BIL-VOID-1')->value('journal_id'));
    }

    public function test_void_receipt_memperlakukan_underscore_pada_nomor_po_sebagai_literal(): void
    {
        $poUnderscore = $this->receivePo('PO_7', 'SKU-VU', 'BIL-VOID-U', 5);
        $poWildcard = $this->receivePo('POX7', 'SKU-VX', 'BIL-VOID-X', 7);

        app(PurchaseOrderService::class)->voidReceipt($poUnderscore);

        // '_' adalah wildcard LIKE. POX7 tidak boleh ikut terpilih ketika void PO_7.
        $this->assertDatabaseHas('journal_headers', ['source_doc_no' => 'BIL-VOID-X', 'transaction_type' => 'Purchase Bill']);
        $this->assertDatabaseHas('inventory_ledgers', ['evidence_number' => 'BIL-VOID-X']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $poWildcard, 'status' => 'RECEIVED']);
        $this->assertDatabaseHas('purchase_order_details', ['purchase_order_id' => $poWildcard, 'qty_received' => 7]);
        $this->assertEquals(7, (float) Product::where('sku', 'SKU-VX')->value('stock_quantity'));
        $this->assertNotNull(DB::table('purchase_bills')->where('bill_number', 'BIL-VOID-X')->value('journal_id'));

        $this->assertDatabaseMissing('journal_headers', ['source_doc_no' => 'BIL-VOID-U']);
        $this->assertDatabaseMissing('inventory_ledgers', ['evidence_number' => 'BIL-VOID-U']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $poUnderscore, 'status' => 'APPROVED']);
        $this->assertEquals(0, (float) Product::where('sku', 'SKU-VU')->value('stock_quantity'));
        $this->assertNull(DB::table('purchase_bills')->where('bill_number', 'BIL-VOID-U')->value('journal_id'));
    }

    private function receivePo(string $poNumber, string $sku, string $billNumber, int $qty): int
    {
        Product::create(['sku' => $sku, 'name' => $sku, 'unit' => 'PCS', 'stock_quantity' => 0, 'average_cost' => 0]);

        $poId = DB::table('purchase_orders')->insertGetId([
            'po_number' => $poNumber, 'transaction_date' => '2026-09-24', 'contact_name' => 'Supplier A',
            'status' => 'APPROVED', 'location_name' => 'Pusat', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detailId = DB::table('purchase_order_details')->insertGetId([
            'purchase_order_id' => $poId, 'item_code' => $sku, 'description' => $sku,
            'price' => 100, 'qty' => $qty, 'amount' => 100 * $qty, 'qty_received' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('purchase_bills')->insert([
            'bill_number' => $billNumber, 'bill_date' => '2026-09-24', 'vendor_name' => 'Supplier A',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(PurchaseOrderService::class)->receivePartialOrder($poId, '2026-09-24', [$detailId => $qty], $billNumber);

        return $poId;
    }
}
