<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentAllocationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function source(string $reference, string $account, string $position): JournalHeader
    {
        $journal = JournalHeader::create(['transaction_date' => '2026-10-08', 'source_doc_no' => $reference]);
        $journal->details()->createMany([
            ['account_code' => $account, 'position' => $position, 'amount' => 100],
            ['account_code' => 'OTHER', 'position' => $position === 'DEBET' ? 'KREDIT' : 'DEBET', 'amount' => 100],
        ]);
        return $journal;
    }

    public function test_unpaid_ar_and_ap_documents_reconcile_with_source_journals(): void
    {
        $ar = $this->source('INV-DOC', config('coa.piutang_usaha'), 'DEBET');
        SalesInvoice::create(['invoice_number' => 'INV-DOC', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer', 'grand_total' => 100, 'journal_id' => $ar->getKey(), 'payment_status' => 'UNPAID']);
        $ap = $this->source('BIL-DOC', config('coa.hutang_usaha'), 'KREDIT');
        \App\Models\PurchaseBill::create(['bill_number' => 'BIL-DOC', 'bill_date' => '2026-10-08', 'vendor_name' => 'Vendor', 'grand_total' => 100, 'journal_id' => $ap->getKey(), 'payment_status' => 'UNPAID']);
        $this->artisan('audit:payment-allocations --documents --json')->expectsOutput('{"checked":0,"findings":0}')->assertExitCode(0);
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertDatabaseCount('system_logs', 0);
    }

    public function test_status_mismatch_is_reported_without_repairing_document(): void
    {
        $source = $this->source('INV-WRONG-STATUS', config('coa.piutang_usaha'), 'DEBET');
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-WRONG-STATUS', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer', 'grand_total' => 100, 'journal_id' => $source->getKey(), 'payment_status' => 'PAID']);
        $before = $invoice->fresh()->getAttributes();
        $this->artisan('audit:payment-allocations --documents --json')
            ->expectsOutput('{"type":"AR","document_id":'.$invoice->id.',"active_paid":"0.00","approved_refund":"0.00","remaining_balance":"100.00","actual_status":"PAID","expected_status":"UNPAID","issues":["document_status_mismatch"]}')
            ->expectsOutput('{"checked":0,"findings":1}')->assertExitCode(1);
        $this->assertSame($before, $invoice->fresh()->getAttributes());
    }

    public function test_reversed_allocations_are_excluded_and_approved_refunds_reduce_balance(): void
    {
        $source = $this->source('INV-REFUND', config('coa.piutang_usaha'), 'DEBET');
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-REFUND', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer', 'grand_total' => 100, 'journal_id' => $source->getKey(), 'payment_status' => 'PAID']);
        foreach (['APPROVE' => 40, 'FAILED_DELIVERY' => 60, 'PENDING_INSPECTION' => 500, 'REJECT' => 500] as $status => $amount) {
            DB::table('sales_returns')->insert(['return_number' => 'SR-'.$status, 'sales_invoice_id' => $invoice->id, 'return_date' => '2026-10-08', 'status' => $status, 'total_refund_amount' => $amount]);
        }
        // Evidence audit deliberately reports this orphan receipt; document scan must still exclude it.
        DB::table('invoice_payment_allocations')->insert(['sales_invoice_id' => $invoice->id, 'journal_id' => 'OLD-RECEIPT', 'amount' => 100, 'allocated_by' => 1, 'reversed_at' => now(), 'reversed_by' => 1, 'reversal_reason' => 'Historical reversal']);
        $this->artisan('audit:payment-allocations --documents --json')
            ->expectsOutput('{"type":"AR","allocation_id":1,"issues":["missing_journal","reversal_missing_journal"]}')
            ->expectsOutput('{"checked":1,"findings":1}')->assertExitCode(1);
        $this->assertDatabaseCount('sales_returns', 4);
    }

    public function test_overallocated_bill_has_negative_remaining_balance(): void
    {
        $source = $this->source('BIL-OVER', config('coa.hutang_usaha'), 'KREDIT');
        $bill = \App\Models\PurchaseBill::create(['bill_number' => 'BIL-OVER', 'bill_date' => '2026-10-08', 'vendor_name' => 'Vendor', 'grand_total' => 100, 'journal_id' => $source->getKey(), 'payment_status' => 'PAID']);
        DB::table('bill_payment_allocations')->insert(['purchase_bill_id' => $bill->id, 'id_payment' => 999, 'journal_id' => 'OVER', 'amount' => 110]);
        $this->artisan('audit:payment-allocations --documents --json')
            ->expectsOutput('{"type":"AP","document_id":'.$bill->id.',"active_paid":"110.00","approved_refund":"0.00","remaining_balance":"-10.00","actual_status":"PAID","expected_status":null,"issues":["negative_remaining_balance"]}')
            ->assertExitCode(1);
        $this->assertSame('PAID', $bill->fresh()->payment_status);
    }

    public function test_active_partial_receipt_reconciles_and_cancelled_document_is_not_reclassified(): void
    {
        $source = $this->source('INV-PARTIAL-DOC', config('coa.piutang_usaha'), 'DEBET');
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-PARTIAL-DOC', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer', 'grand_total' => 100, 'journal_id' => $source->getKey(), 'payment_status' => 'PARTIAL']);
        $receipt = JournalHeader::create(['transaction_date' => '2026-10-08', 'source_doc_no' => $invoice->invoice_number, 'journal_type' => 'MANUAL']);
        $receipt->details()->createMany([
            ['account_code' => config('coa.piutang_usaha'), 'position' => 'KREDIT', 'amount' => 40],
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => 40],
        ]);
        DB::table('invoice_payment_allocations')->insert(['sales_invoice_id' => $invoice->id, 'journal_id' => $receipt->getKey(), 'amount' => 40, 'allocated_by' => 1]);
        $this->artisan('audit:payment-allocations --documents --json')->expectsOutput('{"checked":1,"findings":0}')->assertExitCode(0);
        $invoice->forceFill(['cancelled_at' => now(), 'payment_status' => 'UNPAID'])->save();
        $this->artisan('audit:payment-allocations --documents --json')
            ->expectsOutput('{"type":"AR","document_id":'.$invoice->id.',"active_paid":"40.00","approved_refund":"0.00","remaining_balance":"60.00","actual_status":"UNPAID","expected_status":null,"issues":["cancelled_document_has_active_credit"]}')
            ->assertExitCode(1);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
    }

    public function test_valid_ar_evidence_passes_without_writing_data(): void
    {
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-RECON', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer']);
        $journal = JournalHeader::create(['transaction_date' => '2026-10-08', 'source_doc_no' => $invoice->invoice_number, 'journal_type' => 'MANUAL']);
        $journal->details()->createMany([
            ['account_code' => config('coa.piutang_usaha'), 'position' => 'KREDIT', 'amount' => 40],
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => 40],
        ]);
        DB::table('invoice_payment_allocations')->insert(['sales_invoice_id' => $invoice->id, 'journal_id' => $journal->getKey(), 'amount' => 40, 'allocated_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::table('invoice_payment_allocations')->first();
        $this->artisan('audit:payment-allocations --json')->expectsOutput('{"checked":1,"findings":0}')->assertExitCode(0);
        $this->assertEquals($before, DB::table('invoice_payment_allocations')->first());
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('system_logs', 0);
    }

    public function test_orphaned_ar_and_ap_evidence_returns_failure_and_keeps_records(): void
    {
        DB::table('invoice_payment_allocations')->insert(['sales_invoice_id' => 999, 'journal_id' => 'MISSING-AR', 'amount' => 40, 'allocated_by' => 1]);
        DB::table('bill_payment_allocations')->insert(['purchase_bill_id' => 999, 'id_payment' => 999, 'journal_id' => 'MISSING-AP', 'amount' => 40]);
        $this->artisan('audit:payment-allocations --json')
            ->expectsOutput('{"type":"AR","allocation_id":1,"issues":["missing_document","missing_journal"]}')
            ->expectsOutput('{"type":"AP","allocation_id":1,"issues":["missing_document","missing_payment","missing_journal"]}')
            ->expectsOutput('{"checked":2,"findings":2}')->assertExitCode(1);
        $this->assertDatabaseCount('invoice_payment_allocations', 1);
        $this->assertDatabaseCount('bill_payment_allocations', 1);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_wrong_reference_amount_and_missing_reversal_are_reported(): void
    {
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-RECON-BAD', 'transaction_date' => '2026-10-08', 'contact_name' => 'Buyer']);
        $journal = JournalHeader::create(['transaction_date' => '2026-10-08', 'source_doc_no' => 'OTHER-INVOICE', 'journal_type' => 'MANUAL']);
        $journal->details()->createMany([
            ['account_code' => config('coa.piutang_usaha'), 'position' => 'KREDIT', 'amount' => 30],
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => 20],
        ]);
        DB::table('invoice_payment_allocations')->insert(['sales_invoice_id' => $invoice->id, 'journal_id' => $journal->getKey(), 'amount' => 40, 'allocated_by' => 1, 'reversed_at' => now()]);
        $this->artisan('audit:payment-allocations --json')
            ->expectsOutput('{"type":"AR","allocation_id":1,"issues":["journal_reference_mismatch","invalid_journal_balance","allocation_amount_mismatch","missing_reversal_audit","reversal_missing_journal"]}')
            ->expectsOutput('{"checked":1,"findings":1}')->assertExitCode(1);
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
    }
}