<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Services\GrnReceivingService;
use App\Services\PurchaseOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait GrnScenarios
{
    protected PurchaseOrder $po;
    protected Product $product;
    protected int $detailId;
    protected Company $company;
    protected User $member;

    protected function seedGrnScenario(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.grn_enabled' => true,
            'platform.grn_inventory_account' => '114001', 'platform.grn_payable_account' => '211001',
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $this->company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        $this->member = User::factory()->create(['role' => 'ADMIN']);
        $this->member->companies()->attach($this->company->id, ['is_default' => true]);
        $this->actingAs($this->member);
        foreach ([config('platform.grn_inventory_account') => ['DEBET', 'ASSET', 'Barang Jadi'],
            config('platform.grn_payable_account') => ['KREDIT', 'LIABILITY', 'Utang Usaha']] as $code => $expected) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => $expected[2],
                'coa_type' => $expected[1], 'normal_balance' => $expected[0], 'report_pos' => 'NERACA']);
        }
        $this->product = Product::create(['sku' => 'GRN-SKU', 'name' => 'GRN item', 'stock_quantity' => 0, 'average_cost' => 0]);
        $this->po = PurchaseOrder::create(['po_number' => 'PO-GRN', 'transaction_date' => '2026-09-25',
            'contact_name' => 'Supplier', 'status' => 'APPROVED', 'sub_total' => 1000, 'grand_total' => 1000]);
        $this->detailId = DB::table('purchase_order_details')->insertGetId(['purchase_order_id' => $this->po->id,
            'product_id' => $this->product->id, 'item_code' => $this->product->sku, 'qty' => 100, 'qty_received' => 0,
            'price' => 10, 'amount' => 1000]);
    }

    protected function receive(int $qty, string $bill = 'BIL-GRN-1', ?string $key = null): int
    {
        return app(PurchaseOrderService::class)->receivePartialOrder($this->po->id, '2026-09-25',
            [$this->detailId => $qty], $bill, null, $key ?? (string) Str::uuid());
    }

    protected function assertEmptyReceiving(): void
    {
        foreach (['purchase_receipts', 'purchase_receipt_details', 'purchase_bills', 'purchase_bill_details', 'journal_headers', 'journal_details', 'inventory_ledgers'] as $t) {
            $this->assertSame(0, DB::table($t)->count(), $t);
        }
        $this->assertEquals(0, $this->product->fresh()->stock_quantity);
        $this->assertEquals(0, DB::table('purchase_order_details')->where('id', $this->detailId)->value('qty_received'));
        $this->assertNull($this->po->fresh()->receipt_mode);
    }

    protected function rejected(callable $action): void
    {
        $caught = null;
        try { $action(); } catch (\Throwable $e) { $caught = $e; }
        $this->assertNotNull($caught, 'Operation should have been rejected.');
    }

    public function test_partial_40_plus_60_creates_two_grns_two_bills_and_two_balanced_journals(): void
    {
        $first = $this->receive(40);
        $this->assertSame('PARTIAL', $this->po->fresh()->status);
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);
        $second = $this->receive(60, 'BIL-GRN-2');
        $this->assertNotEquals($first, $second);
        $this->assertSame('RECEIVED', $this->po->fresh()->status);
        $this->assertSame('GRN_V1', $this->po->fresh()->receipt_mode);
        $this->assertEquals(100, $this->product->fresh()->stock_quantity);
        $this->assertEquals(10, $this->product->fresh()->average_cost);
        foreach (['purchase_receipts', 'purchase_bills', 'journal_headers', 'inventory_ledgers'] as $t) { $this->assertSame(2, DB::table($t)->count()); }
        $this->assertEquals(1000, DB::table('purchase_bills')->sum('grand_total'));
        $this->assertEquals(1000, DB::table('journal_details')->where('position', 'DEBET')->sum('amount'));
        $this->assertEquals(1000, DB::table('journal_details')->where('position', 'KREDIT')->sum('amount'));
        foreach (DB::table('purchase_receipts')->get() as $r) {
            $this->assertSame('POSTED', $r->status);
            $this->assertEquals($this->company->id, $r->company_id);
            $this->assertSame($r->journal_id, DB::table('purchase_bills')->where('id', $r->purchase_bill_id)->value('journal_id'));
        }
    }

    public function test_exact_retry_after_complete_returns_same_result_without_side_effects(): void
    {
        $key = (string) Str::uuid();
        $id = $this->receive(100, 'BIL-RETRY', $key);
        $this->assertSame($id, $this->receive(100, 'BIL-RETRY', $key));
        $this->assertSame(1, DB::table('purchase_receipts')->count());
        $this->assertSame(1, DB::table('journal_headers')->count());
        $this->assertEquals(100, $this->product->fresh()->stock_quantity);
    }

    public function test_changed_payload_same_key_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $this->receive(40, 'BIL-KEY', $key);
        $this->rejected(fn () => $this->receive(41, 'BIL-KEY', $key));
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, DB::table('purchase_receipts')->count());
    }

    public function test_same_bill_with_new_key_is_not_received_twice(): void
    {
        $this->receive(40);
        $this->rejected(fn () => $this->receive(40));
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, DB::table('journal_headers')->count());
    }

    public function test_overreceipt_is_rejected_without_partial_writes(): void
    {
        $this->rejected(fn () => $this->receive(101));
        $this->assertEmptyReceiving();
    }

    public function test_negative_fractional_zero_and_missing_key_are_rejected(): void
    {
        foreach ([-1, 0, 1.5] as $qty) {
            $this->rejected(fn () => app(PurchaseOrderService::class)->receivePartialOrder($this->po->id, '2026-09-25',
                [$this->detailId => $qty], 'BIL-BAD', null, (string) Str::uuid()));
        }
        $this->rejected(fn () => app(PurchaseOrderService::class)->receivePartialOrder($this->po->id, '2026-09-25', [$this->detailId => 1], 'BIL-NOKEY'));
        $this->assertEmptyReceiving();
    }

    public function test_foreign_detail_is_rejected(): void
    {
        $this->rejected(fn () => app(PurchaseOrderService::class)->receivePartialOrder($this->po->id, '2026-09-25',
            [$this->detailId + 999 => 1], 'BIL-FOREIGN', null, (string) Str::uuid()));
        $this->assertEmptyReceiving();
    }

    public function test_missing_product_is_rejected_instead_of_posting_inventory_without_stock(): void
    {
        DB::table('purchase_order_details')->where('id', $this->detailId)->update(['item_code' => 'MISSING']);
        $this->rejected(fn () => $this->receive(40));
        $this->assertEmptyReceiving();
    }

    public function test_tax_and_discount_are_rejected_without_guessing_bill_amounts(): void
    {
        DB::table('purchase_order_details')->where('id', $this->detailId)->update(['disc_amount' => 1]);
        $this->rejected(fn () => $this->receive(40));
        $this->assertEmptyReceiving();
    }

    public function test_failure_after_legacy_post_rolls_back_grn_bill_stock_and_journal(): void
    {
        $legacy = new class extends PurchaseOrderService {
            public bool $posted = false;
            public function postThenFail($id, $date, $items, $bill, $due): void {
                $this->receiveLegacy($id, $date, $items, $bill, $due);
                $this->posted = true;
                throw new \RuntimeException('Injected failure after posting');
            }
        };
        $this->rejected(fn () => app(GrnReceivingService::class)->receive($this->po->id, '2026-09-25', [$this->detailId => 40],
            'BIL-FAIL', null, (string) Str::uuid(), fn (...$args) => $legacy->postThenFail(...$args)));
        $this->assertTrue($legacy->posted, 'Failure must be injected after the legacy posting completes.');
        $this->assertEmptyReceiving();
    }

    public function test_flag_off_cannot_reroute_existing_grn_to_legacy_or_void_it(): void
    {
        $this->receive(40);
        config(['platform.grn_enabled' => false]);
        $this->receive(60, 'BIL-DURABLE');
        $this->rejected(fn () => app(PurchaseOrderService::class)->voidReceipt($this->po->id));
        $this->assertSame('RECEIVED', $this->po->fresh()->status);
        $this->assertSame(2, DB::table('purchase_receipts')->count());
        $this->assertEquals(100, $this->product->fresh()->stock_quantity);
    }

    public function test_legacy_bound_po_cannot_switch_to_grn(): void
    {
        $this->po->receipt_mode = 'LEGACY'; $this->po->save();
        $this->rejected(fn () => $this->receive(40));
        $this->assertSame(0, DB::table('purchase_receipts')->count());
        $this->assertEquals(0, $this->product->fresh()->stock_quantity);
    }

    public function test_posted_journal_edit_delete_and_duplicate_evidence_are_rejected(): void
    {
        $this->receive(40);
        $journal = \App\Models\JournalHeader::firstOrFail();
        $this->rejected(fn () => $journal->update(['evidence_number' => 'CHANGED']));
        $this->rejected(fn () => $journal->delete());
        $this->rejected(fn () => \App\Models\JournalHeader::create(['transaction_date' => '2026-09-25', 'source_doc_no' => 'BIL-GRN-1']));
        $this->assertSame(1, DB::table('journal_headers')->count());
        $this->assertSame(2, DB::table('journal_details')->count());
    }

    public function test_standard_purchase_stock_and_journal_match_legacy_baseline(): void
    {
        $snapshot = fn () => [(float) $this->product->fresh()->stock_quantity, (float) $this->product->fresh()->average_cost,
            (float) DB::table('journal_details')->where('position', 'DEBET')->sum('amount'),
            (float) DB::table('journal_details')->where('position', 'KREDIT')->sum('amount'),
            (float) DB::table('inventory_ledgers')->sum('total_cost')];
        DB::beginTransaction();
        try {
            config(['platform.grn_enabled' => false]);
            $this->receive(40);
            $legacy = $snapshot();
        } finally { DB::rollBack(); config(['platform.grn_enabled' => true]); }
        $this->receive(40);
        $this->assertSame($legacy, $snapshot());
        $this->assertSame(1, DB::table('purchase_bills')->count());
        $this->assertSame(1, DB::table('purchase_receipts')->count());
    }

    public function test_generic_inventory_sync_cannot_duplicate_or_reverse_grn_evidence(): void
    {
        $this->receive(40);
        $service = app(\App\Services\InventorySyncService::class);
        $this->rejected(fn () => $service->processStockMovements([['sku' => 'GRN-SKU', 'qty' => 40, 'unit_cost' => 10]],
            'BIL-GRN-1', '2026-09-25', 'BIL'));
        $this->rejected(fn () => $service->reverseStockMovements('BIL-GRN-1', 'BIL'));
        $this->assertEquals(40, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, DB::table('inventory_ledgers')->count());
    }

    public function test_database_restricts_deleting_linked_bill_po_product_and_journal(): void
    {
        $this->receive(40);
        foreach (['purchase_bills', 'purchase_orders', 'products', 'journal_headers', 'purchase_order_details'] as $table) {
            $this->rejected(fn () => DB::table($table)->delete());
            $this->assertSame(1, DB::table($table)->count());
        }
    }
}
