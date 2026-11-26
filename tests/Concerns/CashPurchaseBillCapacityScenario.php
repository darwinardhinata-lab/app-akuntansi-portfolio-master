<?php

namespace Tests\Concerns;

use App\Http\Controllers\PurchaseBillController;
use App\Models\User;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CashPurchaseBillCapacityScenario
{
    public function test_cash_bill_respects_committed_idr3_hold(): void
    {
        $this->assertSame(realpath(base_path('app/Http/Controllers/PurchaseBillController.php')),
            realpath((new \ReflectionClass(PurchaseBillController::class))->getFileName()));
        config(['payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.erp_bank_balance' => true]);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        $user = User::factory()->create(['role' => 'FINANCE']);
        $user->companies()->attach($company->id, ['is_default' => true]);
        foreach (['111101', '610001', '211001', '114001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic', 'normal_balance' => 'DEBET',
                'coa_type' => $code === '111101' ? 'Cash & Bank' : 'Other', 'report_pos' => 'NERACA']);
        }
        $bank = ['id' => 'idr3', 'currency' => 'IDR'];
        $settings = ['erp_cutoff' => '2026-01-01', 'accounts' => [$bank]];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111101', 'active' => true]);
        PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => '500.00'],
            ['account_code' => '610001', 'position' => 'KREDIT', 'amount' => '500.00'],
        ]);
        $id = (string) Str::uuid();
        DB::table('ppl_requests')->insert(['id' => $id, 'doc' => 'CASH-BILL-HOLD', 'company_id' => $company->id,
            'owner_user_id' => $user->id, 'status' => 'bank_prepared', 'version' => 1, 'payload' => '{}']);
        DB::table('ppl_reservations')->insert(['request_id' => $id, 'company_id' => $company->id, 'account_key' => 'idr3',
            'currency' => 'IDR', 'amount' => '400.00', 'status' => 'active']);
        $product = \App\Models\Product::create(['sku' => 'F03-CASH-STOCK', 'name' => 'Synthetic', 'stock_quantity' => 0, 'average_cost' => 0]);
        $before = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
        $this->assertSame('100.00', $before['available']);
        $writer = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        config(['coa.pajak_masukan' => '114001']);
        $post = fn ($amount, $credit = '111101', $tax = '0.00') => $this->actingAs($user)->post(route('purchase-bills.store'), [
            'bill_date' => '2026-01-03', 'vendor_name' => 'F03 synthetic cash', 'credit_account' => $credit, 'tax_amount' => $tax,
            'details' => [['account_code' => '610001', 'amount' => $amount]],
            'items' => [['sku' => $product->sku, 'qty' => 1, 'unit_cost' => $amount]],
        ]);
        $post('100.01');
        $error = session('error');
        config(['database.connections.writer_final' => config('database.connections.writer_test')]);
        DB::setDefaultConnection('writer_final');
        try {
            $reader = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($writer, $reader);
            $after = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
            $snapshot = ['writer' => $writer, 'reader' => $reader, 'balance' => $after, 'error' => $error,
                'bank_credits' => DB::table('journal_details')->where('account_code', '111101')->where('position', 'KREDIT')->pluck('amount'),
                'bills' => DB::table('purchase_bills')->count(), 'stock' => $product->fresh()->stock_quantity];
            if ($path = getenv('CASH_BILL_EVIDENCE')) file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR).PHP_EOL);
            $this->assertSame('100.00', $after['available'], json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->assertSame('400.00', $after['reserved']);
            $this->assertStringContainsString('funds_insufficient', (string) $error);
            foreach (['purchase_bills', 'purchase_bill_details', 'purchase_receipts', 'purchase_receipt_details', 'inventory_ledgers', 'ppl_ap_allocations'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
            $this->assertDatabaseCount('journal_headers', 1);
            $this->assertDatabaseCount('journal_details', 2);
            $this->assertSame(0, (int) $product->fresh()->stock_quantity);
        } finally {
            DB::setDefaultConnection('writer_test');
            DB::purge('writer_final');
        }
        // Same final GL credit at the exact boundary, including tax.
        $post('90.00', '111101', '10.00')->assertSessionHas('success');
        DB::setDefaultConnection('writer_final');
        try {
            $balance = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
            $this->assertSame('0.00', $balance['available']);
            $this->assertSame('400.00', $balance['reserved']);
            $bill = DB::table('purchase_bills')->sole();
            $this->assertSame('PAID', $bill->payment_status);
            $this->assertDatabaseCount('purchase_bill_details', 1);
            $this->assertDatabaseCount('purchase_receipts', 0);
            $this->assertDatabaseCount('purchase_receipt_details', 0);
            $this->assertDatabaseCount('inventory_ledgers', 1);
            $this->assertSame(1, (int) $product->fresh()->stock_quantity);
            $this->assertDatabaseCount('ppl_ap_allocations', 0);
            $this->assertDatabaseCount('journal_headers', 2);
            $this->assertDatabaseCount('journal_details', 5);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $bill->journal_id, 'account_code' => '111101', 'position' => 'KREDIT', 'amount' => '100.00']);
            $this->assertDatabaseHas('ppl_reservations', ['request_id' => $id, 'amount' => '400.00', 'status' => 'active']);
        } finally {
            DB::setDefaultConnection('writer_test');
            DB::purge('writer_final');
        }

        $post('100.01', '211001')->assertSessionHas('success');
        DB::setDefaultConnection('writer_final');
        try {
            $balance = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
            $this->assertSame('0.00', $balance['available']);
            $this->assertSame('400.00', $balance['reserved']);
            $ap = DB::table('purchase_bills')->where('credit_account', '211001')->sole();
            $this->assertSame('UNPAID', $ap->payment_status);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $ap->journal_id, 'account_code' => '211001',
                'position' => 'KREDIT', 'amount' => '100.01']);
            $this->assertDatabaseCount('ppl_ap_allocations', 0);
        } finally {
            DB::setDefaultConnection('writer_test');
            DB::purge('writer_final');
        }
    }
}