<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Support\SalesRevenueAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesSemanticPostingTest extends TestCase
{
    use RefreshDatabase;

    private function seedAccounts(): void
    {
        foreach (['411001' => 'KREDIT', '411002' => 'KREDIT', '113101' => 'DEBET', '114001' => 'DEBET', '510001' => 'DEBET'] as $code => $normal) {
            Account::create([
                'account_code' => (string) $code, 'account_name' => 'Test '.$code,
                'normal_balance' => $normal, 'coa_type' => str_starts_with((string) $code, '411') ? 'Sales' : 'Test',
                'report_pos' => str_starts_with((string) $code, '411') || (string) $code === '510001' ? 'LABA RUGI' : 'NERACA',
            ]);
        }
    }

    public function test_direct_goods_invoice_persists_explicit_semantic_and_posts_correct_revenue(): void
    {
        $this->withoutMiddleware();
        $this->seedAccounts();
        Product::create(['sku' => 'SEM-SKU', 'name' => 'Goods', 'unit' => 'PCS', 'stock_quantity' => 10, 'average_cost' => 20]);
        foreach (['LOCAL' => '411001', 'EXPORT' => '411002'] as $semantic => $code) {
            $this->post(route('invoice.store'), [
                'invoice_number' => 'SEM-'.$semantic, 'sales_semantic' => $semantic,
                'transaction_date' => '2026-10-02', 'contact_name' => 'Customer',
                'details' => [['item_code' => 'SEM-SKU', 'description' => 'Goods', 'qty' => 1, 'price' => 100]],
            ])->assertRedirect(route('invoice.index'))->assertSessionMissing('error');
            $invoice = SalesInvoice::where('invoice_number', 'SEM-'.$semantic)->firstOrFail();
            $this->assertSame($semantic, $invoice->sales_semantic);
            $this->assertSame($code, $invoice->revenue_account_code);
            $this->assertNotNull($invoice->journal_id);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $invoice->journal_id, 'account_code' => $code, 'position' => 'KREDIT', 'amount' => 100]);
        }
    }

    public function test_missing_or_service_semantic_is_rejected_before_stock_changes(): void
    {
        $this->withoutMiddleware();
        foreach ([null, 'SERVICE'] as $semantic) {
            $this->postJson(route('invoice.store'), [
                'invoice_number' => 'INVALID', 'sales_semantic' => $semantic,
                'transaction_date' => '2026-10-02', 'contact_name' => 'Customer',
                'details' => [['qty' => 1, 'price' => 100]],
            ])->assertUnprocessable()->assertJsonValidationErrors('sales_semantic');
        }
        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_wrong_revenue_normal_balance_is_rejected(): void
    {
        $this->seedAccounts();
        Account::where('account_code', '411001')->update(['normal_balance' => 'DEBET']);
        $this->expectException(ValidationException::class);
        SalesRevenueAccount::resolve('LOCAL');
    }
}
