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
            'company_id' => \App\Modules\Platform\Models\Company::sole()->id,
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

    private function authorizedReturnActor(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = \App\Modules\Platform\Models\Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $user = \App\Models\User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $role = \App\Modules\Platform\Models\Role::create(['code' => 'SUPERADMIN', 'name' => 'Test superadmin']);
        $role->permissions()->attach(\App\Modules\Platform\Models\Permission::where('code', 'purchase_return.create')->sole()->id);
        $user->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        $this->actingAs($user);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_posted_inventory_credit_matches_ledger_carrying_value(): void
    {
        $this->authorizedReturnActor();
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
        $this->authorizedReturnActor();
        $this->accounts();
        config(['coa.selisih_retur_pembelian' => null]);
        [$product, $payload] = $this->receipt();
        $this->post(route('purchase-returns.store'), $payload)->assertSessionHas('error');
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        foreach (['purchase_returns', 'purchase_return_details', 'inventory_ledgers', 'journal_headers', 'journal_details'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public static function attributionCases(): array
    {
        return ['unattributed' => [false], 'fully attributed' => [true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('attributionCases')]
    public function test_return_reversal_restores_stock_and_quota_once(bool $attributed): void
    {
        $this->authorizedReturnActor();
        $this->accounts();
        $actor = auth()->user();
        $actor->update(['role' => 'FINANCE']);
        config(['platform.payment_reverse_user_ids' => [$actor->id], 'platform.posting_intents_enabled' => true,
            'payment_plan_v33.bank_workflow' => true]);
        $company = \App\Modules\Platform\Models\Company::sole();
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        [$product, $payload] = $this->receipt();
        $product->update(['average_cost' => 100]);
        $account = config('coa.hutang_usaha');
        DB::table('accounts')->where('account_code', $account)->update(['normal_balance' => 'KREDIT']);
        $journal = \App\Support\PostingService::post(['transaction_date' => '2026-10-06', 'source_doc_no' => 'REV-TEST-BILL'], [
            ['account_code' => config('coa.persediaan'), 'position' => 'DEBET', 'amount' => '250.00'],
            ['account_code' => $account, 'position' => 'KREDIT', 'amount' => '250.00'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'REV-TEST-BILL', 'bill_date' => '2026-10-06',
            'purchase_order_id' => $payload['purchase_order_id'], 'vendor_name' => 'Fixture', 'credit_account' => $account,
            'journal_id' => $journal->getKey(), 'grand_total' => '250.00', 'payment_status' => 'UNPAID']);
        $this->post(route('purchase-returns.store'), $payload)->assertSessionHas('success');
        $id = DB::table('purchase_returns')->sole()->id;
        $url = route('purchase-returns.reverse', $id);
        $body = ['posting_date' => '2026-10-07', 'reason' => 'Synthetic reversal reason'];
        $source = fn () => DB::transaction(fn () => app(\App\Modules\PaymentPlan\Services\PurchaseBillAllocation::class)->source($bill, $company->id));
        if ($attributed) {
            config(['platform.filament_enabled' => true]);
            $role = $actor->platformRoles()->sole();
            $role->permissions()->syncWithoutDetaching(\App\Modules\Platform\Models\Permission::whereIn('code',
                ['erp.panel.access', 'ap.reconcile.propose', 'ap.reconcile.approve', 'ap.reconcile.cancel'])->pluck('id'));
            $approver = \App\Models\User::factory()->create();
            $approver->companies()->attach($company->id, ['is_default' => true]);
            $approver->platformRoles()->attach($role->id, ['company_id' => $company->id]);
            $request = request();
            $request->setLaravelSession(app('session')->driver());
            $ap = app(\App\Modules\PaymentPlan\Services\ApReconciliation::class);
            $detail = DB::table('purchase_return_details')->sole();
            $number = DB::table('purchase_returns')->sole()->return_number;
            $approval = $ap->propose(['purchase_bill_id' => $bill, 'journal_id' => \App\Models\JournalHeader::where('source_doc_no', $number)->sole()->getKey(),
                'purchase_return_detail_id' => $detail->id, 'quantity' => '2', 'amount' => '200.00', 'reason' => 'Synthetic attribution', 'evidence' => $number]);
            $this->actingAs($approver);
            $ap->decide($approval, 1, 'approve', 'Independent synthetic approval');
            $this->actingAs($actor);
            $history = DB::table('ppl_ap_reconciliations')->get()->toJson();
            $decisions = DB::table('ppl_ap_reconciliation_decisions')->get()->toJson();
            $this->assertSame('50.00', $source()['residual']);
        } else {
            $this->assertSame('ap_reconciliation_required', $source()['blocked']);
        }
        $this->postJson($url, $body)->assertOk()->assertJsonPath('actor_id', $actor->id);
        $this->assertEquals(10, $product->fresh()->stock_quantity);
        $this->assertSame('250.00', $source()['residual']);
        $this->assertArrayNotHasKey('blocked', $source());
        $this->postJson($url, $body)->assertOk();
        $this->postJson($url, [...$body, 'reason' => 'Another reversal reason'])->assertConflict();
        $this->assertDatabaseCount('purchase_return_reversals', 1);
        $this->assertDatabaseCount('inventory_ledgers', 2);
        $this->assertDatabaseCount('journal_headers', 3);
        $this->assertDatabaseCount('purchase_return_ap_corrections', (int) $attributed);
        if ($attributed) {
            try {
                $ap->decide($approval, 2, 'cancel', 'Do not rewrite approval history');
                $this->fail('Corrected approval must not be cancelled.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertSame(['attribution_already_compensated'], $e->errors()['reconciliation']);
            }
            $this->assertSame($history, DB::table('ppl_ap_reconciliations')->get()->toJson());
            $this->assertSame($decisions, DB::table('ppl_ap_reconciliation_decisions')->get()->toJson());
        }
        $payload['items'][array_key_first($payload['items'])] = 10;
        $this->post(route('purchase-returns.store'), $payload)->assertSessionHas('success');
        $this->assertEquals(0, $product->fresh()->stock_quantity);
    }
}