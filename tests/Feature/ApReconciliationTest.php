<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_resolve_partial_journal_identity_with_audit_and_capacity_controls(): void
    {
        config(['platform.filament_enabled' => true, 'payment_plan_v33.bank_workflow' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true, 'base_currency' => 'IDR']);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = Role::create(['code' => 'RECON', 'name' => 'Reconciliation']);
        $role->permissions()->attach(Permission::whereIn('code', ['erp.panel.access','ap.reconcile.view','ap.reconcile.propose','ap.reconcile.approve','ap.reconcile.cancel'])->pluck('id'));
        $users = [User::factory()->create(), User::factory()->create()];
        foreach ($users as $user) {
            $user->companies()->attach($company->id, ['is_default' => true]);
            $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        foreach (['211001','111003','610001'] as $code) DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $code, 'coa_type' => 'Other', 'normal_balance' => $code === '211001' ? 'KREDIT' : 'DEBET', 'report_pos' => 'NERACA']);
        $journal = \App\Support\PostingService::post(['source_doc_no' => 'BILL-RECON'], [
            ['account_code' => '610001','position' => 'DEBET','amount' => '200.00'], ['account_code' => '211001','position' => 'KREDIT','amount' => '200.00'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'BILL-RECON','bill_date' => '2026-01-01','vendor_name' => 'Synthetic','credit_account' => '211001','grand_total' => '200.00','journal_id' => $journal->getKey()]);
        $payment = \App\Support\PostingService::post(['source_doc_no' => 'BILL-RECON'], [
            ['account_code' => '211001','position' => 'DEBET','amount' => '50.00'], ['account_code' => '111003','position' => 'KREDIT','amount' => '50.00'],
        ]);
        $this->actingAs($users[0])->withSession([\App\Modules\Platform\Support\CompanyContext::SESSION_KEY => $company->id]);
        $this->get('/erp-panel/ap-reconciliation')->assertOk();
        $service = new ApReconciliation();
        $id = $service->propose(['purchase_bill_id' => $bill,'journal_id' => $payment->getKey(),'amount' => '30.00','reason' => 'Verified supplier receipt','evidence' => 'INTERNAL-RECEIPT-1']);
        try { $service->decide($id, 1, 'approve', 'Verified approval'); $this->fail('Self approval'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('independent_approval_required', $e->errors()['reconciliation'][0]); }
        $this->actingAs($users[1]);
        $service->decide($id, 1, 'approve', 'Verified independently');
        $source = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('170.00', $source['residual']);
        $this->assertEmpty($source['exceptions']);
        $this->assertDatabaseCount('ppl_ap_reconciliation_decisions', 2);
        DB::table('journal_headers')->where('journal_id', $payment->getKey())->update(['notes' => 'Source changed']);
        $changed = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('approved_source_changed', $changed['exceptions'][0]['reason']);
        $this->assertSame('200.00', $changed['residual']);
        DB::table('journal_headers')->where('journal_id', $payment->getKey())->update(['notes' => null]);
        try { $service->decide($id, 1, 'approve', 'Retry stale decision'); $this->fail('Stale approval'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('stale_version', $e->errors()['reconciliation'][0]); }
        $this->actingAs($users[0]);
        $second = $service->propose(['purchase_bill_id' => $bill,'journal_id' => $payment->getKey(),'amount' => '25.00','reason' => 'Second partial allocation','evidence' => 'INTERNAL-RECEIPT-1']);
        $this->actingAs($users[1]);
        try { $service->decide($second, 1, 'approve', 'Reject excess allocation'); $this->fail('Source capacity exceeded'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame('source_overallocated', $e->errors()['reconciliation'][0]); }
        $service->decide($id, 2, 'cancel', 'Correct mistaken attribution');
        $source = DB::transaction(fn () => (new PurchaseBillAllocation())->source($bill, $company->id));
        $this->assertSame('200.00', $source['residual']);
        $this->assertSame('ap_reconciliation_required', $source['blocked']);
        $role->permissions()->detach(Permission::where('code','ap.reconcile.propose')->value('id'));
        try { $service->propose([]); $this->fail('Revoked permission'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }
}