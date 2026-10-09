<?php

namespace Tests\Feature;

use App\Models\JournalHeader;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\InvoicePaymentAllocationService;
use App\Support\SourceJournalProtection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoicePaymentAllocationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): SalesInvoice
    {
        DB::table('accounts')->insert([
            'account_code' => '111101', 'account_name' => 'Bank IDR', 'normal_balance' => 'DEBET',
            'coa_type' => 'Cash & Bank', 'report_pos' => 'NERACA', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $source = JournalHeader::create(['transaction_date' => '2026-10-01', 'transaction_type' => 'Sales Invoice']);
        $source->details()->create(['account_code' => config('coa.piutang_usaha'), 'position' => 'DEBET', 'amount' => 100]);
        return SalesInvoice::create([
            'invoice_number' => 'INV-ALLOC', 'transaction_date' => '2026-10-01', 'contact_name' => 'Customer',
            'grand_total' => 100, 'payment_status' => 'UNPAID', 'journal_id' => $source->getKey(),
        ]);
    }

    public function test_reversal_retains_audit_restores_balance_and_is_idempotent(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(100);
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_reverse_user_ids' => [$user->id]]);
        $service = app(InvoicePaymentAllocationService::class);
        $service->allocate($invoice->id, $receipt->getKey(), $user->id);
        $id = DB::table('invoice_payment_allocations')->value('id');
        $this->actingAs($user);
        $data = ['reason' => 'Transfer penerimaan dibatalkan bank'];
        $this->postJson(route('invoice-payment-allocations.reverse', $id), $data)->assertOk();
        $this->postJson(route('invoice-payment-allocations.reverse', $id), $data)->assertOk();
        $this->assertDatabaseCount('journal_headers', 3);
        $this->assertDatabaseCount('invoice_payment_allocations', 1);
        $allocation = DB::table('invoice_payment_allocations')->first();
        $this->assertEquals($user->id, $allocation->reversed_by);
        $this->assertSame($data['reason'], $allocation->reversal_reason);
        $reversal = JournalHeader::findOrFail($allocation->reversal_journal_id);
        $this->assertEquals(100, $reversal->details()->where('account_code', config('coa.piutang_usaha'))->where('position', 'DEBET')->sum('amount'));
        $this->assertEquals(100, $reversal->details()->where('account_code', '111101')->where('position', 'KREDIT')->sum('amount'));
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
        $view = app(\App\Http\Controllers\AdvancedReportController::class)->arSubledger(\Illuminate\Http\Request::create('/reports/ar-management'));
        $this->assertCount(1, $view->getData()['unpaidInvoices']);
        $this->assertEquals(100, $view->getData()['unpaidInvoices'][0]->remaining_balance);
        try {
            $service->allocate($invoice->id, $receipt->getKey(), $user->id);
            $this->fail('Reversed receipt reused');
        } catch (\RuntimeException $e) {
            $this->assertDatabaseCount('invoice_payment_allocations', 1);
        }
        $service->allocate($invoice->id, $this->receipt(100)->getKey(), $user->id);
        $this->assertSame('PAID', $invoice->fresh()->payment_status);
    }

    public function test_inconsistent_receipt_reversal_leaves_all_financial_and_audit_records_unchanged(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(40);
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_reverse_user_ids' => [$user->id]]);
        app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $receipt->getKey(), $user->id);
        $allocation = DB::table('invoice_payment_allocations')->first();
        // Simulate corrupted historical data, bypassing the normal journal protection.
        DB::table('journal_details')->where('journal_id', $receipt->getKey())
            ->where('position', 'DEBET')->update(['amount' => 39]);
        $headers = DB::table('journal_headers')->orderBy('journal_id')->get()->toArray();
        $details = DB::table('journal_details')->orderBy('id')->get()->toArray();
        $logs = DB::table('system_logs')->get()->toArray();
        $this->actingAs($user)->postJson(route('invoice-payment-allocations.reverse', $allocation->id), [
            'reason' => 'Transfer penerimaan dibatalkan bank',
        ])->assertUnprocessable();
        $this->assertEquals($allocation, DB::table('invoice_payment_allocations')->first());
        $this->assertEquals($headers, DB::table('journal_headers')->orderBy('journal_id')->get()->toArray());
        $this->assertEquals($details, DB::table('journal_details')->orderBy('id')->get()->toArray());
        $this->assertEquals($logs, DB::table('system_logs')->get()->toArray());
        $this->assertSame('PARTIAL', $invoice->fresh()->payment_status);
    }

    public function test_reversal_requires_separate_allowlist_and_valid_reason(): void
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_post_user_ids' => [$user->id]]);
        $this->actingAs($user)->postJson(route('invoice-payment-allocations.reverse', 1), ['reason' => 'Valid reason for reversal'])->assertForbidden();
        config(['platform.payment_reverse_user_ids' => [$user->id]]);
        $this->postJson(route('invoice-payment-allocations.reverse', 1), ['reason' => 'short'])->assertUnprocessable();
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_inconsistent_receipt_reversal_rolls_back_without_audit_or_status_changes(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(40);
        $service = app(InvoicePaymentAllocationService::class);
        $service->allocate($invoice->id, $receipt->getKey(), 1);
        $receipt->details()->where('position', 'DEBET')->update(['amount' => 41]);
        $original = DB::table('invoice_payment_allocations')->first();
        try {
            $service->reverse($original->id, 1, 'Reversal invalid receipt test');
            $this->fail('Inconsistent receipt reversed');
        } catch (\RuntimeException $e) {
            $this->assertEquals($original, DB::table('invoice_payment_allocations')->first());
            $this->assertDatabaseCount('journal_headers', 2);
            $this->assertSame('PARTIAL', $invoice->fresh()->payment_status);
        }
    }

    private function receipt(float $amount, string $ref = 'INV-ALLOC'): JournalHeader
    {
        $journal = JournalHeader::create([
            'transaction_date' => '2026-10-02', 'journal_type' => 'MANUAL', 'source_doc_no' => $ref,
        ]);
        $journal->details()->create(['account_code' => '111101', 'position' => 'DEBET', 'amount' => $amount]);
        $journal->details()->create(['account_code' => config('coa.piutang_usaha'), 'position' => 'KREDIT', 'amount' => $amount]);
        return $journal;
    }

    public function test_partial_and_full_receipts_are_idempotent_and_protected(): void
    {
        $invoice = $this->invoice();
        $first = $this->receipt(40);
        $service = app(InvoicePaymentAllocationService::class);
        $service->allocate($invoice->id, $first->getKey(), 1);
        $service->allocate($invoice->id, $first->getKey(), 1);
        $this->assertDatabaseCount('invoice_payment_allocations', 1);
        $this->assertSame('PARTIAL', $invoice->fresh()->payment_status);
        $service->allocate($invoice->id, $this->receipt(60)->getKey(), 1);
        $this->assertSame('PAID', $invoice->fresh()->payment_status);
        $this->assertEquals(100, DB::table('invoice_payment_allocations')->sum('amount'));
        $this->expectException(\RuntimeException::class);
        SourceJournalProtection::check($first);
    }

    public function test_overpayment_and_wrong_source_are_rejected_without_changes(): void
    {
        $invoice = $this->invoice();
        foreach ([$this->receipt(101), $this->receipt(50, 'OTHER')] as $journal) {
            try {
                app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $journal->getKey(), 1);
                $this->fail('Invalid allocation accepted');
            } catch (\RuntimeException $e) {
                $this->assertDatabaseCount('invoice_payment_allocations', 0);
                $this->assertSame('UNPAID', $invoice->fresh()->payment_status);
            }
        }
    }

    public function test_staff_cannot_allocate_through_http(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'STAFF']))
            ->postJson(route('invoice-payment-allocations.store'), [])->assertForbidden();
        $this->get(route('invoice-payment-allocations.index'))->assertForbidden();
    }

    public function test_browser_form_and_history_require_allowlisted_finance_and_preserve_errors(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(40);
        $user = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($user);
        $this->get(route('invoice-payment-allocations.index'))->assertForbidden();
        config(['platform.payment_post_user_ids' => [$user->id]]);
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag);
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('invoice-payment-allocations.index', ['invoice' => $invoice->id]))->assertOk()
                ->assertSee(__('erp.audit_ar_allocation_title'))->assertSee('name="journal_id"', false);
        }
        $this->post(route('invoice-payment-allocations.store'), ['sales_invoice_id' => $invoice->id, 'journal_id' => $receipt->getKey()])
            ->assertRedirect(route('invoice-payment-allocations.index'))->assertSessionHas('success');
        $this->get(route('invoice-payment-allocations.index'))->assertOk()->assertSee('INV-ALLOC')->assertSee($receipt->getKey());
        $this->from(route('invoice-payment-allocations.index'))->post(route('invoice-payment-allocations.store'), [
            'sales_invoice_id' => $invoice->id, 'journal_id' => $this->receipt(61)->getKey(),
        ])->assertRedirect(route('invoice-payment-allocations.index'))->assertSessionHasErrors('journal_id');
        $this->assertDatabaseCount('invoice_payment_allocations', 1);
    }

    public function test_allocated_invoice_cannot_be_cancelled_even_if_status_is_reset(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(40);
        app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $receipt->getKey(), 1);
        $invoice->update(['payment_status' => 'UNPAID']);
        try {
            app(\App\Services\InvoiceCancellationService::class)->cancel($invoice->id);
            $this->fail('Allocated invoice cancellation accepted');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('erp.audit_invoice_cancel_blocked'), $e->getMessage());
        }
        $this->assertNull($invoice->fresh()->cancelled_at);
        $this->assertDatabaseMissing('journal_headers', ['journal_id' => 'JRN-INV-CANCEL-'.$invoice->id]);
        $this->assertDatabaseHas('invoice_payment_allocations', ['journal_id' => $receipt->getKey(), 'amount' => 40]);
    }

    public function test_allowlisted_finance_can_allocate_and_retry_through_http(): void
    {
        $invoice = $this->invoice();
        $receipt = $this->receipt(40);
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_post_user_ids' => [$user->id]]);
        $this->actingAs($user);
        $data = ['sales_invoice_id' => $invoice->id, 'journal_id' => $receipt->getKey()];
        $this->postJson(route('invoice-payment-allocations.store'), $data)->assertOk();
        $this->postJson(route('invoice-payment-allocations.store'), $data)->assertOk();
        $this->assertDatabaseCount('invoice_payment_allocations', 1);
        $this->assertDatabaseHas('invoice_payment_allocations', ['allocated_by' => $user->id]);
        $this->assertSame('PARTIAL', $invoice->fresh()->payment_status);
    }

    public function test_selector_filters_invoice_and_journal_candidates_and_preserves_pagination(): void
    {
        $invoice = $this->invoice();
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_post_user_ids' => [$user->id]]);
        $this->actingAs($user);
        $allocated = $this->receipt(10);
        app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $allocated->getKey(), $user->id);
        $wrong = $this->receipt(10, 'OTHER');
        $candidate = $this->receipt(20);
        $url = route('invoice-payment-allocations.index', ['invoice' => $invoice->id, 'q' => 'Customer', 'journal_q' => $candidate->getKey()]);
        $this->get($url)->assertOk()->assertViewHas('invoices', fn ($rows) => $rows->count() === 1)
            ->assertViewHas('journals', fn ($rows) => $rows->count() === 1 && $rows->first()->getKey() === $candidate->getKey());
        $this->get(route('invoice-payment-allocations.index', ['q' => 'NO-MATCH']))
            ->assertOk()->assertViewHas('invoices', fn ($rows) => $rows->isEmpty());
        $this->get(route('invoice-payment-allocations.index', ['invoice' => $invoice->id]))
            ->assertOk()->assertViewHas('journals', fn ($rows) => !$rows->contains('journal_id', $allocated->getKey()) && !$rows->contains('journal_id', $wrong->getKey()));
        for ($i = 0; $i < 51; $i++) {
            $this->receipt(1);
        }
        $this->get(route('invoice-payment-allocations.index', ['invoice' => $invoice->id, 'journal_page' => 2]))
            ->assertOk()->assertViewHas('journals', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 2 && str_contains($rows->previousPageUrl(), 'invoice='.$invoice->id));
        $this->getJson(route('invoice-payment-allocations.index', ['journal_q' => str_repeat('x', 101)]))->assertUnprocessable();
    }

    public function test_report_deducts_allocations_and_approved_returns_from_source_invoice(): void
    {
        $invoice = $this->invoice();
        \App\Models\SalesReturn::create([
            'return_number' => 'SR-ALLOC', 'sales_invoice_id' => $invoice->id,
            'return_date' => '2026-10-02', 'status' => 'APPROVE', 'total_refund_amount' => 20,
        ]);
        app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $this->receipt(40)->getKey(), 1);
        $view = app(\App\Http\Controllers\AdvancedReportController::class)->arSubledger(
            \Illuminate\Http\Request::create('/reports/ar-management', 'GET', ['tab' => 'tagihan'])
        );
        $rows = $view->getData()['unpaidInvoices'];
        $this->assertCount(1, $rows);
        $this->assertEquals(40, $rows[0]->remaining_balance);
        app(InvoicePaymentAllocationService::class)->allocate($invoice->id, $this->receipt(40)->getKey(), 1);
        $this->assertSame('PAID', $invoice->fresh()->payment_status);
    }
}