<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\SalesReturnQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReturnQuotaAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_returns_reserve_quota_and_rejected_returns_release_it(): void
    {
        $product = Product::create(['sku' => 'SKU-QUOTA', 'name' => 'Product']);
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-QUOTA', 'transaction_date' => '2026-10-06', 'contact_name' => 'Customer']);
        $invoice->details()->create(['product_id' => $product->id, 'item_code' => $product->sku, 'description' => 'Product', 'price' => 100, 'qty_actual' => 2, 'amount' => 200]);
        $return = SalesReturn::create(['return_number' => 'SR-QUOTA', 'sales_invoice_id' => $invoice->id, 'return_date' => '2026-10-06', 'status' => 'PENDING_INSPECTION']);
        $return->details()->create(['item_code' => $product->sku, 'product_id' => $product->id, 'qty_returned' => 2]);
        $service = app(SalesReturnQuotaService::class);
        try {
            $service->line($invoice, $product->sku, 1);
            $this->fail('Cumulative return accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_return_guard'), $e->getMessage());
        }
        $return->update(['status' => 'REJECT']);
        $this->assertEquals(100, $service->line($invoice, $product->sku, 2)->price);
        $this->expectException(\RuntimeException::class);
        $service->line($invoice, 'ARBITRARY-SKU', 1);
    }

    public function test_invalid_inspection_enums_and_negative_quantities_are_rejected(): void
    {
        $this->withoutMiddleware();
        $this->postJson('/sales-returns/999/process', ['decision' => 'INVALID', 'items' => [['qty_approved' => -1, 'condition' => 'INVALID']]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('inventory_ledgers', 0);
    }
}