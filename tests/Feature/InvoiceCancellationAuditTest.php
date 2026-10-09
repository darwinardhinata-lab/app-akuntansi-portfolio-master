<?php

namespace Tests\Feature;

use App\Models\InventoryLedger;
use App\Models\JournalHeader;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Services\InvoiceCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCancellationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_invoice_cancellation_retains_history_and_is_idempotent(): void
    {
        $journal = JournalHeader::create(['transaction_date' => '2026-09-30', 'source_doc_no' => 'INV-CUSTOM-1']);
        foreach (['DEBET', 'KREDIT'] as $position) {
            $journal->details()->create(['account_code' => '11101', 'position' => $position, 'amount' => 100]);
        }
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-CUSTOM-1', 'transaction_date' => '2026-09-30',
            'contact_name' => 'Audit Customer', 'payment_status' => 'UNPAID', 'journal_id' => $journal->getKey(),
        ]);
        $product = Product::create(['sku' => 'AUDIT-SKU', 'name' => 'Audit Product', 'stock_quantity' => 8, 'average_cost' => 20]);
        InventoryLedger::create([
            'transaction_date' => '2026-09-30', 'evidence_number' => $invoice->invoice_number,
            'product_id' => $product->id, 'type' => 'OUT', 'qty' => 2, 'unit_cost' => 10,
            'total_cost' => 20, 'running_qty' => 8, 'running_value' => 160, 'moving_average_cost' => 20,
        ]);

        $service = app(InvoiceCancellationService::class);
        $cancelled = $service->cancel($invoice->id);
        $service->cancel($invoice->id);

        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $journal->getKey()]);
        $this->assertSame(2, JournalHeader::count());
        $this->assertSame(2, InventoryLedger::count());
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertEquals(18, $product->fresh()->average_cost);
        $this->assertSame('KREDIT', JournalHeader::findOrFail($cancelled->cancellation_journal_id)->details()->first()->position);
    }

    public function test_paid_invoice_is_not_cancelled(): void
    {
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-PAID', 'transaction_date' => '2026-09-30',
            'contact_name' => 'Audit Customer', 'payment_status' => 'PAID',
        ]);
        try {
            app(InvoiceCancellationService::class)->cancel($invoice->id);
            $this->fail('Paid invoice cancellation must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_invoice_cancel_blocked'), $e->getMessage());
        }
        $this->assertNull($invoice->fresh()->cancelled_at);
        $this->assertSame(0, JournalHeader::count());
    }

    public function test_return_linked_invoice_is_not_cancelled(): void
    {
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-RETURN', 'transaction_date' => '2026-09-30',
            'contact_name' => 'Audit Customer', 'payment_status' => 'UNPAID',
        ]);
        \App\Models\SalesReturn::create([
            'return_number' => 'SR-AUDIT', 'sales_invoice_id' => $invoice->id,
            'return_date' => '2026-10-01', 'status' => 'PENDING_INSPECTION',
        ]);
        $this->expectExceptionMessage(__('erp.audit_invoice_cancel_blocked'));
        app(InvoiceCancellationService::class)->cancel($invoice->id);
    }

    public function test_missing_source_journal_leaves_invoice_unchanged(): void
    {
        $invoice = SalesInvoice::create([
            'invoice_number' => 'INV-MISSING', 'transaction_date' => '2026-09-30',
            'contact_name' => 'Audit Customer', 'payment_status' => 'UNPAID',
        ]);
        try {
            app(InvoiceCancellationService::class)->cancel($invoice->id);
            $this->fail('Missing journal must block cancellation.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_invoice_journal_invalid'), $e->getMessage());
        }
        $this->assertNull($invoice->fresh()->cancelled_at);
        $this->assertSame(0, JournalHeader::count());
    }
}