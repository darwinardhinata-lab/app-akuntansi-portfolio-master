<?php

namespace Tests\Feature;

use App\Models\PurchaseBill;
use App\Models\PurchaseReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_endpoints_are_not_registered(): void
    {
        foreach (['users.show', 'tax.show', 'purchase-bills.get-pos', 'purchase-returns.process'] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    public function test_purchase_document_details_render_read_only_in_all_locales(): void
    {
        $this->withoutMiddleware();
        $bill = PurchaseBill::create(['bill_number' => 'BIL-DETAIL', 'bill_date' => '2026-10-08',
            'vendor_name' => 'Audit Supplier', 'grand_total' => 123, 'sub_total' => 123, 'payment_status' => 'UNPAID']);
        $bill->details()->create(['account_code' => '61100', 'description' => 'Account-based charge', 'amount' => 123]);
        $po = \App\Models\PurchaseOrder::create(['po_number' => 'PO-DETAIL', 'transaction_date' => '2026-10-08', 'contact_name' => 'Audit Supplier', 'status' => 'RECEIVED']);
        $return = PurchaseReturn::create(['purchase_order_id' => $po->id, 'return_number' => 'PR-DETAIL', 'return_date' => '2026-10-08',
            'status' => 'COMPLETED', 'total_return_amount' => 50]);
        $return->details()->create(['item_code' => 'SKU-AUDIT', 'description' => 'Returned item', 'qty_returned' => 2, 'price' => 25, 'subtotal' => 50]);
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('purchase-bills.show', $bill->id))->assertOk()->assertSee('Audit Supplier')
                ->assertSee('Account-based charge')->assertSee('61100')->assertDontSee('name="_method"', false);
            $this->get(route('purchase-returns.show', $return->id))->assertOk()->assertSee('SKU-AUDIT')
                ->assertSee('COMPLETED')->assertDontSee('name="decision"', false);
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('inventory_ledgers', 0);
        $this->get(route('purchase-bills.show', 99999))->assertNotFound();
        $this->get(route('purchase-returns.show', 99999))->assertNotFound();
    }

    public function test_document_detail_routes_require_authentication(): void
    {
        $this->get(route('purchase-bills.show', 1))->assertRedirect(route('login'));
        $this->get(route('purchase-returns.show', 1))->assertRedirect(route('login'));
    }
}