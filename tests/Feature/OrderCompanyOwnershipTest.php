<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Support\CompanyContext;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'platform.order_company_scope_enabled' => true,
            'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false,
        ]);
        $this->company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        $this->member = User::factory()->create(['role' => 'ADMIN']);
        $this->member->companies()->attach($this->company->id, ['is_default' => true]);
        $this->actingAs($this->member);
    }

    public function test_manual_po_persists_server_owner_sku_and_amount_without_posting(): void
    {
        $this->post(route('po.store'), $this->payload('po'))->assertRedirect(route('po.index'))
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-A2', 'company_id' => $this->company->id, 'grand_total' => 250]);
        $this->assertDatabaseHas('purchase_order_details', ['item_code' => 'SKU-A2', 'qty' => 2, 'amount' => 250, 'product_id' => null]);
        $this->assertNoPosting();
    }

    public function test_manual_so_persists_server_owner_without_posting(): void
    {
        $this->post(route('so.store'), $this->payload('so'))->assertRedirect(route('so.index'))
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertDatabaseHas('sales_orders', ['so_number' => 'SO-A2', 'company_id' => $this->company->id, 'grand_total' => 250]);
        $this->assertDatabaseHas('sales_order_details', ['item_code' => 'SKU-A2', 'qty' => 2, 'amount' => 250]);
        $this->assertNoPosting();
    }

    public function test_po_edit_preserves_owner_and_recalculates_manual_details(): void
    {
        $this->post(route('po.store'), $this->payload('po'))->assertSessionMissing('error');
        $id = DB::table('purchase_orders')->value('id');
        $payload = $this->payload('po');
        $payload['details'][0]['qty'] = 3;
        $this->put(route('po.update', $id), $payload)->assertRedirect(route('po.index'))->assertSessionMissing('error');
        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'company_id' => $this->company->id, 'grand_total' => 375]);
        $this->assertDatabaseCount('purchase_order_details', 1);
        $this->assertDatabaseHas('purchase_order_details', ['purchase_order_id' => $id, 'amount' => 375]);
        $this->assertNoPosting();
    }

    public function test_so_edit_preserves_owner(): void
    {
        $this->post(route('so.store'), $this->payload('so'))->assertSessionMissing('error');
        $id = DB::table('sales_orders')->value('id');
        $payload = $this->payload('so');
        $payload['contact_name'] = 'Updated customer';
        $this->put(route('so.update', $id), $payload)->assertRedirect(route('so.index'))->assertSessionMissing('error');
        $this->assertDatabaseHas('sales_orders', ['id' => $id, 'company_id' => $this->company->id, 'contact_name' => 'Updated customer']);
        $this->assertNoPosting();
    }

    public function test_manual_forms_require_a_matching_company_context(): void
    {
        foreach (['po', 'so'] as $type) {
            $payload = $this->payload($type);
            unset($payload['context_company_id']);
            $this->post(route($type.'.store'), $payload)->assertStatus(409);
            $payload['context_company_id'] = $this->company->id + 99;
            $this->post(route($type.'.store'), $payload)->assertStatus(409);
        }
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_client_cannot_submit_company_id_even_when_it_matches(): void
    {
        foreach (['po', 'so'] as $type) {
            $this->post(route($type.'.store'), array_merge($this->payload($type), ['company_id' => $this->company->id]))
                ->assertStatus(422);
        }
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_missing_sku_is_validation_error_before_any_header_is_written(): void
    {
        foreach (['po', 'so'] as $type) {
            $payload = $this->payload($type);
            unset($payload['details'][0]['item_code']);
            $this->post(route($type.'.store'), $payload)->assertSessionHasErrors('details.0.item_code');
        }
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_non_member_and_revoked_membership_cannot_access_orders(): void
    {
        $this->withSession([CompanyContext::SESSION_KEY => $this->company->id]);
        $this->member->companies()->detach($this->company->id);
        $this->get(route('po.index'))->assertStatus(409);
        $this->post(route('so.store'), $this->payload('so'))->assertStatus(409);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_missing_default_requires_company_picker_then_explicit_choice(): void
    {
        $this->member->companies()->updateExistingPivot($this->company->id, ['is_default' => false]);
        $this->get(route('po.index'))->assertStatus(409);
        $this->get(route('platform.company.edit'))->assertOk();
        $this->post(route('platform.company.update'), ['company_id' => $this->company->id])->assertRedirect();
        $this->get(route('po.create'))->assertOk()->assertSee('context_company_id');
    }

    public function test_second_company_blocks_operations_instead_of_mixing_shared_ledgers(): void
    {
        Company::create(['code' => 'OTHER', 'name' => 'Other', 'active' => false]);
        $this->get(route('po.index'))->assertStatus(409);
        $this->post(route('so.store'), $this->payload('so'))->assertStatus(409);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_inactive_or_non_mgi_company_blocks_operations(): void
    {
        $this->company->update(['active' => false]);
        $this->get(route('po.index'))->assertStatus(409);
        $this->company->update(['active' => true, 'code' => 'BBW']);
        $this->get(route('so.index'))->assertStatus(409);
    }

    public function test_legacy_sync_and_customs_must_remain_disabled(): void
    {
        config(['platform.legacy_sync_enabled' => true]);
        $this->get(route('po.index'))->assertStatus(409);
        config(['platform.legacy_sync_enabled' => false, 'customs.enabled' => true]);
        $this->get(route('so.index'))->assertStatus(409);
    }

    public function test_unowned_documents_are_hidden_from_edit_and_cannot_be_changed_or_deleted(): void
    {
        foreach (['po', 'so'] as $type) {
            $table = $type === 'po' ? 'purchase_orders' : 'sales_orders';
            $id = $this->unowned($type);
            $this->get(route($type.'.edit', $id))->assertNotFound();
            $this->put(route($type.'.update', $id), $this->payload($type))->assertNotFound();
            $this->delete(route($type.'.destroy', $id))->assertNotFound();
            $this->assertDatabaseHas($table, ['id' => $id, 'company_id' => null]);
        }
        $this->assertNoPosting();
    }

    public function test_model_scope_groups_or_conditions_and_bulk_updates(): void
    {
        $mine = PurchaseOrder::create($this->header('po', 'PO-MINE'));
        $old = $this->unowned('po');
        $ids = PurchaseOrder::where('po_number', 'missing')->orWhere('status', 'APPROVED')->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
        PurchaseOrder::whereIn('id', [$mine->id, $old])->update(['contact_name' => 'Changed']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $old, 'contact_name' => 'Test partner']);
        $this->assertDatabaseHas('purchase_orders', ['id' => $mine->id, 'contact_name' => 'Changed']);
    }

    public function test_console_model_create_and_update_or_create_assign_owner(): void
    {
        foreach ([PurchaseOrder::class => 'po', SalesOrder::class => 'so'] as $class => $type) {
            $order = $class::create($this->header($type, strtoupper($type).'-IMPORT'));
            $updated = $class::updateOrCreate([$type.'_number' => $order->{$type.'_number'}], ['contact_name' => 'Import updated']);
            $this->assertSame($order->id, $updated->id);
            $this->assertEquals($this->company->id, $updated->company_id);
        }
    }

    public function test_existing_owner_cannot_be_cleared_by_model_save(): void
    {
        $po = PurchaseOrder::create($this->header('po', 'PO-IMMUTABLE'));
        $po->forceFill(['company_id' => null]);
        $this->expectException(ValidationException::class);
        $po->save();
    }

    public function test_forced_foreign_owner_on_creation_is_rejected(): void
    {
        $so = new SalesOrder($this->header('so', 'SO-FORGED'));
        $so->forceFill(['company_id' => $this->company->id + 99]);
        $this->expectException(ValidationException::class);
        $so->save();
    }

    public function test_wrong_party_role_cannot_be_linked_through_model_import(): void
    {
        $party = Party::create(['company_id' => $this->company->id, 'code' => 'CUSTOMER-ONLY', 'legal_name' => 'Customer', 'active' => true]);
        $party->roles()->create(['role' => 'CUSTOMER', 'active' => true]);
        $this->expectException(ValidationException::class);
        PurchaseOrder::create(array_merge($this->header('po', 'PO-WRONG-PARTY'), ['party_id' => $party->id]));
    }

    public function test_po_receipt_rejects_unowned_header_before_stock_and_journal(): void
    {
        $id = $this->unowned('po');
        try {
            app(PurchaseOrderService::class)->receivePartialOrder($id, '2026-09-24', []);
            $this->fail('Unowned PO must be rejected.');
        } catch (ModelNotFoundException $e) {
            $this->assertNoPosting();
            $this->assertDatabaseCount('purchase_bills', 0);
        }
    }

    public function test_so_shipping_rejects_unowned_header_before_stock_and_journal(): void
    {
        $id = $this->unowned('so');
        try {
            app(SalesOrderService::class)->createInvoiceAndShip($id, '2026-09-24', [], []);
            $this->fail('Unowned SO must be rejected.');
        } catch (ModelNotFoundException $e) {
            $this->assertNoPosting();
            $this->assertDatabaseCount('sales_invoices', 0);
        }
    }

    public function test_invoice_delete_cannot_mutate_an_unowned_linked_so(): void
    {
        $id = $this->unowned('so');
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-UNOWNED', 'sales_order_id' => $id,
            'transaction_date' => '2026-09-24', 'contact_name' => 'Test partner', 'grand_total' => 0]);
        $this->delete(route('invoice.destroy', $invoice->id))->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('sales_orders', ['id' => $id, 'company_id' => null]);
        $this->assertNoPosting();
    }

    public function test_flag_off_keeps_legacy_model_behavior_for_staged_installation(): void
    {
        config(['platform.order_company_scope_enabled' => false]);
        $po = PurchaseOrder::create($this->header('po', 'PO-LEGACY'));
        $this->assertNull($po->fresh()->company_id);
        $this->assertSame(1, PurchaseOrder::count());
    }

    public function test_login_renders_configured_company_name(): void
    {
        auth()->logout();
        config(['app.name' => 'PT. Magicase Group Indonesia']);
        $this->get(route('login'))->assertOk()->assertSee('PT. Magicase Group Indonesia');
    }

    public function test_csv_po_import_assigns_owner_and_reimport_does_not_duplicate_details(): void
    {
        $csv = "Tanggal;Purchase Order No.;Item Code;Description;Contact;Harga Satuan;Qty;Diskon;Pajak;Total;Subtotal;Grand Total;Lokasi\n"
            ."2026-09-24;PO-CSV;SKU-CSV;Test item;Test vendor;125;2;0;0;250;250;250;Pusat\n";
        for ($i = 0; $i < 2; $i++) {
            $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('po.csv', $csv);
            $this->post(route('po.import'), ['file_csv' => $file])->assertRedirect()->assertSessionMissing('error');
        }
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('purchase_order_details', 1);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-CSV', 'company_id' => $this->company->id]);
        $this->assertDatabaseHas('purchase_order_details', ['item_code' => 'SKU-CSV', 'qty' => 2, 'amount' => 250]);
        $this->assertNoPosting();
    }

    public function test_return_cannot_use_detail_from_a_different_or_unowned_po(): void
    {
        $mine = PurchaseOrder::create($this->header('po', 'PO-RETURN'));
        $oldId = $this->unowned('po');
        $detailId = DB::table('purchase_order_details')->insertGetId(['purchase_order_id' => $oldId,
            'item_code' => 'OLD-SKU', 'qty' => 1, 'price' => 125, 'amount' => 125]);
        $this->post(route('purchase-returns.store'), ['purchase_order_id' => $mine->id,
            'return_date' => '2026-09-24', 'items' => [$detailId => 1]])->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Detail PO #'.$detailId.' tidak ditemukan.'));
        $this->assertDatabaseCount('purchase_returns', 0);
        $this->assertDatabaseCount('purchase_return_details', 0);
        $this->assertNoPosting();
    }

    public function test_readiness_refuses_sqlite_without_mutating_orders(): void
    {
        $this->artisan('platform:check-order-ownership')->assertExitCode(1);
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('sales_orders', 0);
    }

    private function payload(string $type): array
    {
        return array_merge($this->header($type, strtoupper($type).'-A2'), [
            'context_company_id' => $this->company->id,
            'receiver_name' => 'Test receiver',
            'details' => [['item_code' => 'SKU-A2', 'description' => 'Test item', 'qty' => 2, 'price' => 125]],
        ]);
    }

    private function header(string $type, string $number): array
    {
        return [$type.'_number' => $number, 'transaction_date' => '2026-09-24',
            'contact_name' => 'Test partner', 'status' => 'APPROVED', 'location_name' => 'Pusat'];
    }

    private function unowned(string $type): int
    {
        return DB::table($type === 'po' ? 'purchase_orders' : 'sales_orders')->insertGetId(
            array_merge($this->header($type, strtoupper($type).'-UNOWNED'), ['company_id' => null])
        );
    }

    private function assertNoPosting(): void
    {
        foreach (['journal_headers', 'journal_details', 'inventory_ledgers', 'products'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
