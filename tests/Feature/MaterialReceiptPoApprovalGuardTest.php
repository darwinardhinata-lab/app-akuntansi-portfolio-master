<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Modules\Manufacturing\Imports\MaterialReceiptImport;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialReceiptPoApprovalGuardTest extends TestCase
{
    use RefreshDatabase;

    private MaterialProcurementService $procurement;

    private MaterialReceiptService $receipts;

    private User $requester;

    private User $buyer;

    private Yarn $yarn;

    private Supplier $supplier;

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
        // FIX: mapping fixture sama dengan baseline receipt; tidak mengubah konfigurasi COA produksi.
        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        // FIX: request HTTP fixture memakai membership MGI sah agar mencapai guard receipt.
        $this->buyer->companies()->attach($company->id, ['is_default' => true]);
        foreach ([
            ['114003', 'Bahan baku', 'ASSET', 'DEBET', 'raw_material_inventory'],
            ['117008', 'PPN Masukan', 'ASSET', 'DEBET', 'input_vat'],
            ['211001', 'Utang Usaha', 'LIABILITY', 'KREDIT', 'accounts_payable'],
        ] as [$code, $name, $type, $balance, $semantic]) {
            Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => $type, 'normal_balance' => $balance, 'report_pos' => 'NERACA']);
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $semantic, 'account_code' => $code, 'active' => true]);
        }
        $this->yarn = Yarn::create(['yarn_code' => 'YARN-GUARD', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $this->supplier = Supplier::create(['supplier_code' => 'SUP-GUARD', 'supplier_name' => 'Supplier Guard', 'supplier_type' => 'RAW_MATERIAL']);
    }

    public function test_service_and_http_reject_draft_submitted_rejected_without_mutation(): void
    {
        foreach (['DRAFT', 'SUBMITTED', 'REJECTED'] as $status) {
            $po = $this->order($status);
            $item = $this->receiptItem($po, 1);
            $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($po), [$item]), 'belum APPROVED');
            $this->assertRejected(fn () => $this->receipts->createAndPost($this->header(), [$item]), 'belum APPROVED');
            $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($po), [$this->item(1)]), 'belum APPROVED');
            $before = $this->snapshot();
            $this->actingAs($this->buyer)->post(route('mfg.material-receipts.store'), $this->header($po) + ['items' => [$item]])
                ->assertRedirect()->assertSessionHas('error', fn ($error) => str_contains($error, 'belum APPROVED'));
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_approved_end_to_end_receipt_matches_baseline_posting(): void
    {
        $po = $this->order('APPROVED');
        $this->assertSame('APPROVED', $po->status);
        $receipt = $this->receipts->createAndPost($this->header($po) + ['tax_amount' => 100], [$this->receiptItem($po, 10)]);
        $this->assertDatabaseHas('mfg_material_receipts', ['id' => $receipt->id, 'status' => 'POSTED', 'gross_amount' => 1000, 'tax_amount' => 100]);
        $this->assertEquals(10, $this->yarn->fresh()->stock_quantity);
        $this->assertEquals(100, $this->yarn->fresh()->average_cost);
        $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => $receipt->receipt_number, 'type' => 'IN', 'total_cost' => 1000]);
        $lines = DB::table('journal_details')->where('journal_id', $receipt->journal_id)->orderBy('position')->orderBy('account_code')->get();
        $this->assertSame(['114003', '117008', '211001'], $lines->pluck('account_code')->all());
        $this->assertSame(['DEBET', 'DEBET', 'KREDIT'], $lines->pluck('position')->all());
        $this->assertEquals([1000, 100, 1100], $lines->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
    }

    public function test_partial_then_remaining_receipt_succeeds_status_stays_approved_and_extra_is_rejected(): void
    {
        $po = $this->order('APPROVED');
        $this->receipts->createAndPost($this->header($po), [$this->receiptItem($po, 4)]);
        $this->assertSame('APPROVED', $po->fresh()->status);
        $this->assertEquals(4, $po->details()->sole()->qty_received);
        $this->receipts->createAndPost($this->header($po), [$this->receiptItem($po, 6)]);
        $this->assertSame('APPROVED', $po->fresh()->status);
        $this->assertEquals(10, $po->details()->sole()->qty_received);
        $this->assertEquals(10, $this->yarn->fresh()->stock_quantity);
        $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($po), [$this->receiptItem($po, 1)]), 'melebihi sisa PO');
    }

    public function test_mismatched_or_missing_po_detail_is_rejected_before_mutation(): void
    {
        $first = $this->order('APPROVED');
        $other = $this->order('APPROVED');
        $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($first), [$this->receiptItem($other, 1)]), 'tidak sesuai');
        $missing = $this->item(1) + ['po_detail_id' => 999999];
        $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($first), [$missing]), 'tidak ditemukan');
        $before = $this->snapshot();
        $this->actingAs($this->buyer)->post(route('mfg.material-receipts.store'), $this->header($first) + ['items' => [$this->receiptItem($other, 1)]])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'tidak sesuai'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_two_po_references_with_one_draft_reject_entire_receipt(): void
    {
        $approved = $this->order('APPROVED');
        $draft = $this->order('DRAFT');
        $this->assertRejected(fn () => $this->receipts->createAndPost($this->header(), [
            $this->receiptItem($approved, 1), $this->receiptItem($draft, 1),
        ]), 'belum APPROVED');
    }

    public function test_revision_after_received_quantity_is_still_rejected(): void
    {
        $po = $this->order('APPROVED');
        $this->receipts->createAndPost($this->header($po), [$this->receiptItem($po, 1)]);
        // FIX: kondisi legacy tidak konsisten untuk menguji larangan revisi setelah receipt, bukan fixture sukses.
        $po->update(['approval_status' => 'REJECTED']);
        $this->assertRejected(fn () => $this->procurement->reviseOrder($po->id, 'Alasan revisi yang valid', $this->buyer->id), 'sudah diterima');
        $this->assertSame('REJECTED', $po->fresh()->approval_status);
        $this->assertSame(0, $po->fresh()->revision_no);
    }

    public function test_import_remains_non_po_and_does_not_claim_po_protection(): void
    {
        $draft = $this->order('DRAFT');
        $import = new MaterialReceiptImport;
        $import->collection(new Collection([[
            'REF-GUARD', '2026-10-05', $this->supplier->supplier_code, 'DOC-IMPORT-GUARD',
            '0', 'YARN', $this->yarn->yarn_code, 'Cotton Yarn', '1', 'KGS', '100', '',
        ]]));
        $this->assertSame([], $import->getErrors());
        $this->assertSame(1, $import->getSuccessCount());
        $this->assertDatabaseHas('mfg_material_receipts', ['po_id' => null, 'supplier_doc_no' => 'DOC-IMPORT-GUARD']);
        $this->assertEquals(0, $draft->details()->sole()->qty_received);
        $this->assertSame('DRAFT', $draft->fresh()->approval_status);
        $this->assertEquals(1, $this->yarn->fresh()->stock_quantity);
    }

    public function test_revised_po_is_draft_and_cannot_receive_before_reapproval(): void
    {
        $po = $this->order('REJECTED');
        $this->procurement->reviseOrder($po->id, 'Harga telah diperiksa kembali', $this->buyer->id);
        $this->assertSame('DRAFT', $po->fresh()->approval_status);
        $this->assertRejected(fn () => $this->receipts->createAndPost($this->header($po), [$this->receiptItem($po, 1)]), 'belum APPROVED');
    }

    private function order(string $status): MaterialPurchaseOrder
    {
        // FIX: seluruh PO sukses dibentuk lewat workflow PR/PO nyata, tanpa set status langsung.
        $pr = $this->procurement->createRequest(['request_date' => '2026-10-05', 'created_by' => $this->requester->id], [$this->item(10)]);
        $this->procurement->submitRequest($pr->id, $this->requester->id);
        $this->procurement->approveRequest($pr->id, $this->buyer->id);
        $po = $this->procurement->createOrderFromRequest($pr->id, $this->supplier->id, [$this->item(10) + ['source_request_detail_id' => $pr->details()->sole()->id]], ['po_date' => '2026-10-05', 'created_by' => $this->buyer->id]);
        if ($status !== 'DRAFT') {
            $this->procurement->submitOrder($po->id, $this->buyer->id);
        }
        if ($status === 'APPROVED') {
            $this->procurement->approveOrder($po->id, $this->requester->id);
        } elseif ($status === 'REJECTED') {
            $this->procurement->rejectOrder($po->id, 'Harga perlu diperiksa', $this->requester->id);
        }

        return $po->fresh();
    }

    private function header(?MaterialPurchaseOrder $po = null): array
    {
        return ['receipt_date' => '2026-10-05', 'supplier_id' => $this->supplier->id, 'po_id' => $po?->id];
    }

    private function item(float $qty): array
    {
        return ['item_type' => 'YARN', 'yarn_id' => $this->yarn->id, 'item_name' => 'Cotton Yarn', 'qty' => $qty, 'unit' => 'KGS', 'rate' => 100];
    }

    private function receiptItem(MaterialPurchaseOrder $po, float $qty): array
    {
        return $this->item($qty) + ['po_detail_id' => $po->details()->sole()->id];
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['mfg_material_receipts', 'mfg_material_receipt_details', 'mfg_material_ledgers', 'inventory_ledgers', 'journal_headers', 'journal_details', 'mfg_material_purchase_orders', 'mfg_material_purchase_order_details'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->toJson();
        }
        $snapshot['yarn'] = $this->yarn->fresh()->getRawOriginal();

        return $snapshot;
    }

    private function assertRejected(callable $operation, string $message): void
    {
        $before = $this->snapshot();
        $caught = false;
        $guardCase = $message !== 'melebihi sisa PO' && $message !== 'sudah diterima';
        $active = true;
        $writes = [];
        // FIX: penolakan guard harus sebelum SQL mutasi, bukan hanya rollback setelah posting.
        DB::listen(function ($query) use (&$active, &$writes) {
            if ($active && preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        try {
            $operation();
        } catch (\Exception $e) {
            $caught = true;
            $this->assertStringContainsString($message, $e->getMessage());
        } finally {
            $active = false;
        }
        $this->assertTrue($caught, 'Penolakan wajib terjadi sebelum mutasi receipt.');
        if ($guardCase) {
            $this->assertSame([], $writes, 'Guard tidak boleh melakukan SQL mutasi sebelum menolak.');
        }
        $this->assertSame($before, $this->snapshot());
    }
}
