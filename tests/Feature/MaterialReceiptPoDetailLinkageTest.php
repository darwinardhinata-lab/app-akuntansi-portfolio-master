<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialReceiptPoDetailLinkageTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $buyer;

    private Yarn $yarn;

    private Supplier $supplier;

    private MaterialProcurementService $procurement;

    private MaterialReceiptService $receipts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->procurement = app(MaterialProcurementService::class);
        $this->receipts = app(MaterialReceiptService::class);
        $this->requester = User::factory()->create();
        $this->buyer = User::factory()->create();
        config([
            'platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false,
            'platform.pr_create_user_ids' => [(string) $this->requester->id],
            'platform.pr_approve_user_ids' => [(string) $this->buyer->id],
            'platform.po_create_user_ids' => [(string) $this->buyer->id],
            'platform.po_approve_user_ids' => [(string) $this->requester->id],
        ]);
        // FIX: fixture memakai mapping baseline receipt, bukan perubahan COA produksi.
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $this->buyer->companies()->attach($company->id, ['is_default' => true]);
        foreach ([
            ['114003', 'ASSET', 'DEBET', 'raw_material_inventory'],
            ['117008', 'ASSET', 'DEBET', 'input_vat'],
            ['211001', 'LIABILITY', 'KREDIT', 'accounts_payable'],
        ] as [$code, $type, $balance, $semantic]) {
            Account::create(['account_code' => $code, 'account_name' => $semantic, 'coa_type' => $type, 'normal_balance' => $balance, 'report_pos' => 'NERACA']);
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $semantic, 'account_code' => $code, 'active' => true]);
        }
        $this->yarn = Yarn::create(['yarn_code' => 'YARN-LINK', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $this->supplier = Supplier::create(['supplier_code' => 'SUP-LINK', 'supplier_name' => 'Supplier Link', 'supplier_type' => 'RAW_MATERIAL']);
    }

    public function test_header_requires_every_detail_and_detail_requires_header(): void
    {
        $po = $this->order();
        $this->reject($this->header($po), [$this->item(1)], 'po_detail_id');
        $this->reject($this->header(), [$this->linked($po, 1)], 'po_id');
        $this->reject($this->header($po), [$this->linked($po, 1), $this->item(1)], 'po_detail_id');
        $before = $this->snapshot();
        $this->actingAs($this->buyer)->post(route('mfg.material-receipts.store'), $this->header($po) + ['items' => [$this->item(1)]])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'po_detail_id'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_single_and_aggregate_over_receipt_rejected_before_any_write(): void
    {
        $po = $this->order();
        $this->reject($this->header($po), [$this->linked($po, 11)], $po->po_number, ['10.00', '11.00']);
        $this->reject($this->header($po), [$this->linked($po, 6), $this->linked($po, 5)], $po->po_number, ['10.00', '11.00']);
    }

    public function test_detail_without_header_cannot_bypass_received_quantity_tracking(): void
    {
        $po = $this->order();
        $this->reject($this->header(), [$this->linked($po, 1)], 'po_id');
        $this->assertEquals(0, $po->details()->sole()->qty_received);
    }

    public function test_duplicate_detail_lines_cannot_exceed_remaining_in_total(): void
    {
        $po = $this->order();
        $this->reject($this->header($po), [$this->linked($po, 6), $this->linked($po, 5)], $po->po_number, ['10.00', '11.00']);
    }

    public function test_decimal_aggregate_point_one_three_times_fits_point_three(): void
    {
        $po = $this->order(0.3);
        $this->receipts->createAndPost($this->header($po), [$this->linked($po, 0.1), $this->linked($po, 0.1), $this->linked($po, 0.1)]);
        $this->assertEqualsWithDelta(0.3, $po->details()->sole()->qty_received, 0.000001);
        $this->assertEqualsWithDelta(0.3, $this->yarn->fresh()->stock_quantity, 0.000001);
    }

    public function test_decimal_aggregate_point_one_plus_point_three_exceeds_point_three(): void
    {
        $po = $this->order(0.3);
        $this->reject($this->header($po), [$this->linked($po, 0.1), $this->linked($po, 0.3)], $po->po_number, ['0.30', '0.40']);
    }

    public function test_detail_ownership_type_master_and_exact_unit_must_match(): void
    {
        $po = $this->order();
        $other = $this->order();
        $this->reject($this->header($po), [$this->linked($other, 1)], 'tidak sesuai');
        $this->reject($this->header($po), [array_replace($this->linked($po, 1), ['po_detail_id' => 999999])], 'tidak ditemukan');
        $otherYarn = Yarn::create(['yarn_code' => 'OTHER-LINK', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        foreach ([['item_type' => 'FABRIC'], ['yarn_id' => $otherYarn->id], ['unit' => 'kgs'], ['unit' => 'KGS ']] as $change) {
            $this->reject($this->header($po), [array_replace($this->linked($po, 1), $change)], 'tidak sesuai');
        }
    }

    public function test_real_workflow_http_receives_four_then_six_with_baseline_posting(): void
    {
        $po = $this->order();
        $this->actingAs($this->buyer);
        foreach ([4, 6] as $qty) {
            $this->post(route('mfg.material-receipts.store'), $this->header($po) + ['items' => [$this->linked($po, $qty)]])
                ->assertRedirect()->assertSessionHas('success');
        }
        $this->assertEquals(10, $po->details()->sole()->qty_received);
        $this->assertSame('APPROVED', $po->fresh()->status);
        $this->assertEquals(10, $this->yarn->fresh()->stock_quantity);
        $this->assertEquals(100, $this->yarn->fresh()->average_cost);
        $this->assertEquals(1000, DB::table('mfg_material_ledgers')->sum('total_cost'));
        $this->assertEquals(1000, DB::table('journal_details')->where('position', 'DEBET')->sum('amount'));
        $this->assertEquals(1000, DB::table('journal_details')->where('position', 'KREDIT')->sum('amount'));
        $this->reject($this->header($po), [$this->linked($po, 1)], $po->po_number, ['0.00', '1.00']);
    }

    public function test_supplier_and_quantity_precision_rejected_before_mutation(): void
    {
        $po = $this->order();
        $other = Supplier::create(['supplier_code' => 'SUP-OTHER', 'supplier_name' => 'Other', 'supplier_type' => 'RAW_MATERIAL']);
        $this->reject(array_replace($this->header($po), ['supplier_id' => $other->id]), [$this->linked($po, 1)], 'Supplier');
        foreach ([0, -1, 0.001] as $qty) {
            $this->reject($this->header($po), [$this->linked($po, $qty)], 'Qty');
        }
    }

    public function test_pure_non_po_receipt_still_succeeds(): void
    {
        $receipt = $this->receipts->createAndPost($this->header(), [$this->item(2)]);
        $this->assertNull($receipt->po_id);
        $this->assertEquals(2, $this->yarn->fresh()->stock_quantity);
        $this->assertEquals(100, $this->yarn->fresh()->average_cost);
    }

    public function test_mixed_approved_and_draft_po_rejected_without_partial_mutation(): void
    {
        $approved = $this->order();
        $draft = $this->order(10, false);
        $this->reject($this->header(), [$this->linked($approved, 1), $this->linked($draft, 1)], 'po_id');
    }

    public function test_void_restores_po_quantity_and_preserves_history_idempotently(): void
    {
        $this->buyer->update(['role' => 'FINANCE']);
        config(['platform.mrn_void_user_ids' => [$this->buyer->id]]);
        $this->actingAs($this->buyer);
        $po = $this->order();
        $receipt = $this->receipts->createAndPost($this->header($po), [$this->linked($po, 10)]);
        $this->assertTrue($this->receipts->void($receipt->id, 'Audit void correction'));
        $this->assertSame('VOIDED', $receipt->fresh()->status);
        $this->assertEquals(0, $this->yarn->fresh()->stock_quantity);
        $this->assertEquals(0, $po->details()->sole()->qty_received);
        $this->assertDatabaseCount('mfg_material_ledgers', 2);
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertNotNull($receipt->fresh()->journal_id);
        $audit = $receipt->fresh();
        $this->assertSame('Audit void correction', $audit->void_reason);
        $this->assertEquals($this->buyer->id, $audit->voided_by);
        $this->assertNotNull($audit->voided_at);
        $this->assertNotNull($audit->reversal_journal_id);
        $this->assertTrue($this->receipts->void($receipt->id, 'Audit void correction'));
        $this->assertDatabaseCount('journal_headers', 2);
        $this->assertSame($audit->getAttributes(), $receipt->fresh()->getAttributes());
    }

    public function test_void_rejects_later_material_movement_without_mutation(): void
    {
        $this->buyer->update(['role' => 'FINANCE']);
        config(['platform.mrn_void_user_ids' => [$this->buyer->id]]);
        $this->actingAs($this->buyer);
        $po = $this->order();
        $receipt = $this->receipts->createAndPost($this->header($po), [$this->linked($po, 10)]);
        $this->receipts->createAndPost($this->header(), [$this->item(1)]);
        $before = $this->snapshot();
        try {
            $this->receipts->void($receipt->id, 'Audit void correction');
            $this->fail('Later movement must block void.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('mutasi lanjutan', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_void_restores_opening_mac_for_duplicate_material_lines(): void
    {
        $this->buyer->update(['role' => 'FINANCE']);
        config(['platform.mrn_void_user_ids' => [$this->buyer->id]]);
        $this->actingAs($this->buyer);
        $this->yarn->update(['stock_quantity' => 5, 'average_cost' => 50]);
        $po = $this->order();
        $receipt = $this->receipts->createAndPost($this->header($po), [$this->linked($po, 4), $this->linked($po, 6)]);
        $this->receipts->void($receipt->id, 'Audit void correction');
        $this->assertEquals(5, $this->yarn->fresh()->stock_quantity);
        $this->assertEqualsWithDelta(50, $this->yarn->fresh()->average_cost, 0.01);
        $this->assertEquals(0, $po->details()->sole()->qty_received);
        $this->assertDatabaseCount('mfg_material_ledgers', 3);
    }

    public function test_http_void_form_visibility_and_audit_in_three_locales(): void
    {
        $po = $this->order();
        $receipt = $this->receipts->createAndPost($this->header($po), [$this->linked($po, 10)]);
        $this->actingAs($this->buyer);
        $this->get(route('mfg.material-receipts.show', $receipt->id))->assertOk()->assertDontSee('name="reason"', false);
        $this->buyer->update(['role' => 'FINANCE']);
        config(['platform.mrn_void_user_ids' => [$this->buyer->id]]);
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('mfg.material-receipts.show', $receipt->id))->assertOk()->assertSee('name="reason"', false);
        }
        $this->post(route('mfg.material-receipts.void', $receipt->id), ['reason' => 'Supplier document correction'])->assertSessionHas('success');
        $this->assertSame('Supplier document correction', $receipt->fresh()->void_reason);
        $this->post(route('mfg.material-receipts.void', $receipt->id), ['reason' => 'Different retry reason'])->assertSessionHas('success');
        $this->assertSame('Supplier document correction', $receipt->fresh()->void_reason);
        $this->assertDatabaseCount('journal_headers', 2);
    }

    private function order(float $qty = 10, bool $approve = true): MaterialPurchaseOrder
    {
        $pr = $this->procurement->createRequest(['request_date' => '2026-10-05', 'created_by' => $this->requester->id], [$this->item($qty)]);
        $this->procurement->submitRequest($pr->id, $this->requester->id);
        $this->procurement->approveRequest($pr->id, $this->buyer->id);
        $po = $this->procurement->createOrderFromRequest($pr->id, $this->supplier->id, [$this->item($qty) + ['source_request_detail_id' => $pr->details()->sole()->id]], ['po_date' => '2026-10-05', 'created_by' => $this->buyer->id]);
        if ($approve) {
            $this->procurement->submitOrder($po->id, $this->buyer->id);
            $this->procurement->approveOrder($po->id, $this->requester->id);
        }

        return $po->fresh();
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarn->id, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 100];
    }

    private function linked(MaterialPurchaseOrder $po, float $qty): array
    {
        return $this->item($qty) + ['po_detail_id' => $po->details()->sole()->id];
    }

    private function header(?MaterialPurchaseOrder $po = null): array
    {
        return ['receipt_date' => '2026-10-05', 'supplier_id' => $this->supplier->id, 'po_id' => $po?->id];
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['mfg_material_receipts', 'mfg_material_receipt_details', 'mfg_material_ledgers', 'inventory_ledgers', 'journal_headers', 'journal_details', 'mfg_material_purchase_order_details', 'mfg_yarns'] as $table) {
            $result[$table] = DB::table($table)->get()->toJson();
        }

        return $result;
    }

    private function reject(array $header, array $items, string $message, array $numbers = []): void
    {
        $before = $this->snapshot();
        $writes = [];
        $active = true;
        DB::listen(function ($query) use (&$active, &$writes) {
            if ($active && preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $caught = false;
        try {
            $this->receipts->createAndPost($header, $items);
        } catch (\Exception $e) {
            $caught = true;
            $this->assertStringContainsString($message, $e->getMessage());
            foreach ($numbers as $number) {
                $this->assertStringContainsString($number, $e->getMessage());
            }
        } finally {
            $active = false;
        }
        $this->assertTrue($caught, 'Receipt tidak sah harus ditolak.');
        $this->assertSame([], $writes, 'Penolakan wajib mendahului SQL mutasi.');
        $this->assertSame($before, $this->snapshot());
    }
}
