<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\PaymentPlan\Services\BankPaymentControl;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPlanApAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_ap_payment_debits_existing_payable_not_expense_or_vat(): void
    {
        config(['payment_plan_v33.posting' => true, 'platform.posting_intents_enabled' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        $user = User::factory()->create();
        foreach (['111003', '211001', '610001'] as $code) DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Fixture', 'coa_type' => 'Other', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        $source = PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '100.00'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '100.00'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'AP-FIXTURE', 'bill_date' => '2026-01-01', 'vendor_name' => 'Synthetic', 'credit_account' => '211001', 'grand_total' => '100.00', 'journal_id' => $source->getKey(), 'payment_status' => 'UNPAID']);
        $id = 'b86874d8-f1bc-427a-b216-727be6e21651';
        $payload = ['id' => $id, 'doc' => 'PP-AP', 'category' => 'raw_ap', 'purchaseBillId' => $bill, 'paidDate' => '2026-01-03',
            'settlement' => ['base' => '40.00', 'vat' => '0.00', 'fees' => '0.00', 'withholding' => '0.00', 'gross' => '40.00', 'debit' => '40.00', 'sourceId' => 'idr3']];
        DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $company->id, 'owner_user_id' => $user->id, 'doc' => 'PP-AP', 'status' => 'paid', 'version' => 1, 'payload' => json_encode($payload)]);
        $allocator = new PurchaseBillAllocation();
        DB::transaction(fn () => $allocator->reserve($payload, $company->id));
        $this->assertSame('60.00', DB::transaction(fn () => $allocator->source($bill, $company->id))['residual']);
        $tooMuch = $payload;
        $tooMuch['settlement']['gross'] = '61.00';
        try {
            DB::transaction(fn () => $allocator->reserve($tooMuch, $company->id));
            $this->fail('AP residual exceeded.');
        } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $exception) {
            $this->assertSame('overpayment', $exception->rule);
        }
        DB::table('ppl_ap_allocations')->where('request_id', $id)->update(['status' => 'consumed']);
        // Mutable legacy source after actual payment must not change the frozen AP counterpart.
        DB::table('purchase_bills')->where('id', $bill)->update(['payment_status' => 'PAID', 'credit_account' => '610001']);
        DB::table('ppl_payment_events')->insert(['id' => 'e1523b8f-a2d2-431a-a932-08da27d52a11', 'request_id' => $id, 'company_id' => $company->id, 'confirmed_by' => $user->id, 'snapshot' => json_encode($payload), 'created_at' => now()]);
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111003', 'active' => true]);
        $snapshot = DB::table('ppl_ap_allocations')->where('request_id', $id)->value('source_snapshot');
        $this->assertSame('211001', json_decode($snapshot, true)['account_code']);
        DB::table('ppl_ap_allocations')->where('request_id', $id)->update(['source_snapshot' => null]);
        try {
            DB::transaction(fn () => (new BankPaymentControl())->after($payload, $payload + ['journal' => []], 'post_external', ['company_id' => $company->id, 'id' => (string) $user->id]));
            $this->fail('Historical allocation without approved source evidence must be reconciled.');
        } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $exception) {
            $this->assertSame('ap_source_snapshot_reconciliation_required', $exception->rule);
        }
        $this->assertDatabaseCount('journal_headers', 1);
        DB::table('ppl_ap_allocations')->where('request_id', $id)->update(['source_snapshot' => $snapshot]);
        $result = DB::transaction(fn () => (new BankPaymentControl())->after($payload, $payload + ['journal' => []], 'post_external', ['company_id' => $company->id, 'id' => (string) $user->id]));
        $journalId = $result['journal']['journalId'];
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journalId, 'account_code' => '211001', 'position' => 'DEBET', 'amount' => '40.00']);
        $this->assertDatabaseMissing('journal_details', ['journal_id' => $journalId, 'account_code' => '610001']);
        $this->assertSame(2, DB::table('journal_details')->where('journal_id', $journalId)->count());
        DB::table('purchase_bills')->where('id', $bill)->update(['payment_status' => 'PARTIAL', 'credit_account' => '211001']);
        // The Payment Plan journal is already represented by its allocation: count it once.
        DB::table('journal_headers')->where('journal_id', $journalId)->update(['bill_id' => $bill]);
        $manual = PostingService::post(['bill_id' => $bill, 'source_doc_no' => 'AP-FIXTURE'], [
            ['account_code' => '211001', 'position' => 'DEBET', 'amount' => '10.00'],
            ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '10.00'],
        ]);
        $projection = DB::transaction(fn () => $allocator->source($bill, $company->id));
        $this->assertSame('50.00', $projection['residual']);
        $this->assertCount(2, $projection['events']);
        PostingService::post(['bill_id' => $bill, 'source_doc_no' => 'AP-FIXTURE', 'journal_type' => 'REVERSAL'], [
            ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '10.00'],
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '10.00'],
        ]);
        $this->assertSame('60.00', DB::transaction(fn () => $allocator->source($bill, $company->id))['residual']);
        // Matching text alone is not an authorized invoice allocation.
        DB::table('journal_headers')->where('journal_id', $manual->getKey())->update(['bill_id' => null]);
        $projection = DB::transaction(fn () => $allocator->source($bill, $company->id));
        $this->assertSame('ap_reconciliation_required', $projection['blocked']);
        $this->assertSame('invoice_identity_unverified', $projection['exceptions'][0]['reason']);
        DB::table('journal_headers')->where('journal_id', $manual->getKey())->update(['bill_id' => $bill]);
        $po = DB::table('purchase_orders')->insertGetId(['po_number' => 'PO-RECON', 'transaction_date' => '2026-01-01', 'contact_name' => 'Synthetic']);
        DB::table('purchase_bills')->where('id', $bill)->update(['purchase_order_id' => $po]);
        $returnId = DB::table('purchase_returns')->insertGetId(['return_number' => 'PR-RECON', 'purchase_order_id' => $po,
            'return_date' => '2026-01-02', 'status' => 'COMPLETED', 'total_return_amount' => '5.00']);
        $projection = DB::transaction(fn () => $allocator->source($bill, $company->id));
        $this->assertSame('60.00', $projection['residual']);
        $this->assertSame('return_invoice_allocation_required', $projection['exceptions'][0]['reason']);
        $this->assertSame('purchase_return:'.$returnId, $projection['exceptions'][0]['source']);
        try {
            DB::transaction(fn () => $allocator->reserve($payload, $company->id));
            $this->fail('Ambiguous return must block a new reservation.');
        } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $exception) {
            $this->assertSame('ap_reconciliation_required', $exception->rule);
        }
        $migration = require database_path('migrations/2026_10_27_160000_add_ap_allocation_source_snapshot.php');
        try {
            $migration->down();
            $this->fail('Used snapshots must not be discarded by rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('AP source snapshots must be retained.', $exception->getMessage());
        }
    }
}