<?php

namespace Tests\Feature\ApAllocation;

use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\ApAllocationService;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $actor;
    private int $bill;
    private int $po;
    private int $line;
    private int $receipt;
    private string $journal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->company = Company::create(['code' => 'MGI', 'name' => 'Fixture', 'active' => true]);
        $this->actor = User::factory()->create(['role' => 'accountant']);
        $this->actor->companies()->attach($this->company->id);
        foreach (['111003', '211001', '610001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Fixture', 'coa_type' => 'Other',
                'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        }
        $this->journal = PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '400.00'],
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '400.00'],
        ])->getKey();
        $this->po = DB::table('purchase_orders')->insertGetId(['company_id' => $this->company->id,
            'po_number' => 'PO-R2', 'transaction_date' => '2026-01-01', 'contact_name' => 'Fixture']);
        $this->line = DB::table('purchase_order_details')->insertGetId(['purchase_order_id' => $this->po,
            'item_code' => 'SKU', 'qty' => 100, 'qty_received' => 40, 'price' => '10.00', 'amount' => '1000.00']);
        $this->bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'BILL-R2', 'bill_date' => '2026-01-01',
            'purchase_order_id' => $this->po, 'vendor_name' => 'Fixture', 'credit_account' => '211001',
            'grand_total' => '400.00', 'journal_id' => $this->journal, 'payment_status' => 'UNPAID']);
        $this->receipt = DB::table('purchase_receipts')->insertGetId(['company_id' => $this->company->id,
            'purchase_order_id' => $this->po, 'purchase_bill_id' => $this->bill, 'receipt_number' => 'GRN-R2',
            'receipt_date' => '2026-01-01', 'status' => 'POSTED', 'journal_id' => $this->journal]);
        DB::table('purchase_receipt_details')->insert(['purchase_receipt_id' => $this->receipt,
            'purchase_order_detail_id' => $this->line, 'item_code' => 'SKU', 'qty_received' => 40, 'unit_cost' => '10.00', 'amount' => '400.00']);
    }

    private function match(): array
    {
        return (new ApAllocationService())->validateThreeWayMatch($this->bill, $this->company->id);
    }

    public function test_missing_currency_schema_rejects_r2_without_rewriting_r1_history(): void
    {
        foreach (['journal_headers', 'journal_details'] as $table) {
            if ($table === 'journal_details') {
                \Illuminate\Support\Facades\Schema::table($table, fn ($blueprint) => $blueprint->dropIndex(['currency_code']));
            }
            \Illuminate\Support\Facades\Schema::table($table, function ($blueprint) {
                $blueprint->dropColumn('currency_code');
            });
            for ($retry = 0; $retry < 2; $retry++) {
                $this->rejects('ap_currency_schema_required', fn () => $this->match());
                $source = DB::transaction(fn () => (new PurchaseBillAllocation())->source($this->bill, $this->company->id));
                $this->assertSame('400.00', $source['residual']);
                $this->assertSame([], $source['exceptions']);
            }
        }
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('journal_details', 2);
        $this->assertDatabaseCount('ppl_events', 0);
    }

    private function rejects(string $rule, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected financial guard: '.$rule);
        } catch (RuleViolation $exception) {
            $this->assertSame($rule, $exception->rule);
            $this->assertSame(409, $exception->httpStatus);
        }
    }

    public function test_partial_receipt_matches_actual_schema_without_mutating_state(): void
    {
        $this->assertTrue($this->match()['matched']);
        $this->assertSame([], $this->match()['exceptions']);
        $this->assertDatabaseHas('purchase_bills', ['id' => $this->bill, 'match_status' => 'pending']);
        $this->assertDatabaseCount('ppl_events', 0);
        $this->assertDatabaseCount('journal_headers', 1);
    }

    public function test_grn_must_be_posted_and_linked_to_this_bill(): void
    {
        DB::table('purchase_receipts')->where('id', $this->receipt)->update(['status' => 'DRAFT']);
        $this->assertContains('no_posted_bill_grn', array_column($this->match()['exceptions'], 'reason'));
        DB::table('purchase_receipts')->where('id', $this->receipt)->update(['status' => 'POSTED', 'purchase_bill_id' => null]);
        $this->assertFalse($this->match()['matched']);
    }

    public function test_price_tolerance_uses_decimal_difference_not_comparison_sign(): void
    {
        DB::table('purchase_bills')->where('id', $this->bill)->update(['match_tolerance_price' => '2.00']);
        DB::table('purchase_order_details')->where('id', $this->line)->update(['price' => '12.00']);
        $this->assertTrue($this->match()['matched']);
        DB::table('purchase_order_details')->where('id', $this->line)->update(['price' => '12.01']);
        $this->assertContains('price_tolerance_exceeded', array_column($this->match()['exceptions'], 'reason'));
    }

    public function test_duplicate_sku_cannot_substitute_another_po_detail(): void
    {
        DB::table('purchase_receipt_details')->update(['purchase_order_detail_id' => null]);
        $this->assertContains('po_detail_identity_mismatch', array_column($this->match()['exceptions'], 'reason'));
    }

    public function test_quantity_and_grn_totals_are_checked(): void
    {
        DB::table('purchase_order_details')->where('id', $this->line)->update(['qty' => 39]);
        $this->assertContains('qty_tolerance_exceeded', array_column($this->match()['exceptions'], 'reason'));
        DB::table('purchase_receipt_details')->update(['amount' => '399.00']);
        $reasons = array_column($this->match()['exceptions'], 'reason');
        $this->assertContains('grn_line_total_mismatch', $reasons);
        $this->assertContains('bill_grn_total_mismatch', $reasons);
    }

    public function test_missing_recognition_journal_is_rejected(): void
    {
        DB::table('purchase_bills')->where('id', $this->bill)->update(['journal_id' => null]);
        $this->rejects('posted_purchase_bill_required', fn () => $this->match());
    }

    public function test_currency_and_company_boundaries_fail_closed(): void
    {
        DB::table('journal_headers')->where('journal_id', $this->journal)->update(['currency_code' => 'USD']);
        $this->assertContains('non_idr_currency_not_supported', array_column($this->match()['exceptions'], 'reason'));
        $this->rejects('ap_company_binding_required', fn () => (new ApAllocationService())->calculateOutstanding($this->bill, 999));
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'active' => true]);
        $this->rejects('ap_company_binding_required', fn () => $this->match());
    }

    public function test_residual_counts_r1_reservation_once_and_release_restores_it(): void
    {
        $id = (string) Str::uuid();
        $payload = ['id' => $id, 'purchaseBillId' => $this->bill, 'settlement' => ['gross' => '150.00']];
        DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $this->company->id, 'owner_user_id' => $this->actor->id,
            'doc' => 'PP-R2', 'status' => 'draft', 'version' => 1, 'payload' => json_encode($payload)]);
        DB::transaction(fn () => (new PurchaseBillAllocation())->reserve($payload, $this->company->id));
        $service = new ApAllocationService();
        $this->assertSame('250.00', $service->calculateOutstanding($this->bill, $this->company->id));
        DB::table('ppl_ap_allocations')->where('request_id', $id)->update(['status' => 'consumed']);
        $this->assertSame('250.00', $service->calculateOutstanding($this->bill, $this->company->id));
        DB::table('ppl_ap_allocations')->where('request_id', $id)->update(['status' => 'released']);
        $this->assertSame('400.00', $service->calculateOutstanding($this->bill, $this->company->id));
        $this->assertDatabaseCount('ppl_ap_payment_allocations', 0);
    }

    public function test_parallel_financial_mutations_and_waiver_are_blocked_without_writes(): void
    {
        $service = new ApAllocationService();
        for ($retry = 0; $retry < 2; $retry++) {
            $this->rejects('r1_payment_workflow_required', fn () => $service->allocatePayment([]));
            $this->rejects('r1_payment_workflow_required', fn () => $service->postAllocationJournal(1, $this->company->id, $this->actor));
            $this->rejects('r1_payment_workflow_required', fn () => $service->reverseAllocation(1, $this->company->id, $this->actor, 'reason'));
            $this->rejects('ap_match_waiver_workflow_required', fn () => $service->waiveMatch($this->bill, $this->company->id, $this->actor, 'reason'));
        }
        $this->assertDatabaseCount('journal_headers', 1);
        $this->assertDatabaseCount('ppl_ap_payment_allocations', 0);
        $this->assertDatabaseCount('ppl_ap_allocations', 0);
        $this->assertDatabaseCount('ppl_events', 0);
    }
}