<?php

namespace Tests\Feature\CustomsReports;

use App\Models\User;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\InternalReportSyncService;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternalReportSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['customs.enabled' => false, 'platform.order_company_scope_enabled' => false]);
        Http::preventStrayRequests();
    }

    private function period(string $type): ReportPeriod
    {
        return app(ReportPeriodService::class)->createDraft($type, 9, 2026);
    }

    public function test_all_seven_report_types_sync_empty_sources_without_h2h(): void
    {
        foreach (ReportPeriod::ALL_TYPES as $type) {
            $period = $this->period($type);
            app(InternalReportSyncService::class)->sync($period);
            $this->assertSame(0, $period->lines()->count());
        }
        Http::assertNothingSent();
    }

    public function test_assets_sync_on_create_view_and_finalize_without_duplicates(): void
    {
        DB::table('assets')->insert([
            'journal_detail_id' => 1, 'asset_code' => 'AST-1', 'asset_name' => 'Mesin',
            'purchase_date' => '2026-08-01', 'purchase_price' => 5000, 'quantity' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $period = $this->period(ReportPeriod::TYPE_MUTASI_BARANG_MODAL);
        $this->assertSame('2.00', $period->lines()->first()->saldo_awal);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        DB::table('assets')->where('asset_code', 'AST-1')->update(['quantity' => 3]);
        $this->get(route('customs-reports.show', $period))->assertOk();
        $this->assertSame('2.00', $period->lines()->first()->saldo_akhir);
        $this->assertSame(1, $period->lines()->count());
        app(ReportPeriodService::class)->finalize($period);
        DB::table('assets')->where('asset_code', 'AST-1')->delete();
        app(InternalReportSyncService::class)->sync($period->fresh());
        $this->assertSame('3.00', $period->lines()->first()->saldo_akhir);
        Http::assertNothingSent();
    }

    public function test_material_opening_stock_is_included_without_month_movements(): void
    {
        DB::table('mfg_yarns')->insert(['id' => 1, 'yarn_code' => 'Y-1', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        DB::table('mfg_material_ledgers')->insert([
            'transaction_date' => '2026-08-01', 'evidence_number' => 'OPEN', 'item_type' => 'YARN', 'item_id' => 1,
            'type' => 'IN', 'qty' => 10, 'unit_cost' => 1, 'total_cost' => 10,
            'running_qty' => 10, 'running_value' => 10, 'moving_average_cost' => 1,
        ]);
        $period = $this->period(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU);
        $this->assertSame('10.00', $period->lines()->first()->saldo_awal);
        $this->assertSame('10.00', $period->lines()->first()->saldo_akhir);
        $this->assertSame('0.00', $period->lines()->first()->jumlah_pemasukan_barang);
    }

    public function test_finished_goods_opening_stock_survives_a_month_without_movements(): void
    {
        $product = \App\Models\Product::create(['sku' => 'FG-OPEN', 'name' => 'Kaos', 'unit' => 'PCS']);
        DB::table('mfg_work_orders')->insert(['spk_number' => 'SPK-OPEN', 'order_date' => '2026-08-01', 'product_id' => $product->id]);
        DB::table('inventory_ledgers')->insert([
            'transaction_date' => '2026-08-15', 'evidence_number' => 'SPK-OPEN', 'product_id' => $product->id,
            'type' => 'IN', 'qty' => 12, 'unit_cost' => 10, 'total_cost' => 120,
            'running_qty' => 12, 'running_value' => 120, 'moving_average_cost' => 10,
        ]);
        $period = $this->period(ReportPeriod::TYPE_MUTASI_BARANG_JADI);
        app(InternalReportSyncService::class)->sync($period);
        $line = $period->lines()->firstOrFail();
        $this->assertSame('12.00', $line->saldo_awal);
        $this->assertSame('12.00', $line->saldo_akhir);
        $this->assertSame('0.00', $line->jumlah_pemasukan_barang);
        $this->assertSame(1, $period->lines()->count());
        $this->assertDatabaseCount('inventory_ledgers', 1);
        Http::assertNothingSent();
    }

    public function test_sync_rolls_back_existing_snapshot_when_source_is_invalid(): void
    {
        DB::table('mfg_yarns')->insert(['id' => 1, 'yarn_code' => 'Y-ROLLBACK', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        DB::table('mfg_material_ledgers')->insert([
            'transaction_date' => '2026-09-01', 'evidence_number' => 'ML-ROLLBACK', 'item_type' => 'YARN', 'item_id' => 1,
            'type' => 'IN', 'qty' => 5, 'unit_cost' => 1, 'total_cost' => 5,
            'running_qty' => 5, 'running_value' => 5, 'moving_average_cost' => 1,
        ]);
        $period = $this->period(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU);
        $originalId = $period->lines()->firstOrFail()->id;
        DB::table('mfg_yarns')->delete();
        try {
            app(InternalReportSyncService::class)->sync($period);
            $this->fail('Invalid source must fail synchronization.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
        }
        $this->assertSame($originalId, $period->lines()->firstOrFail()->id);
        $this->assertSame('5.00', $period->lines()->firstOrFail()->saldo_akhir);
    }

    public function test_reject_fabric_uses_source_quantity_and_unit_and_removes_deleted_events(): void
    {
        DB::table('mfg_fabrics')->insert(['id' => 1, 'fabric_code' => 'F-1', 'fabric_type' => 'Cotton', 'state' => 'FINISHED', 'unit' => 'KGS']);
        DB::table('mfg_suppliers')->insert(['id' => 1, 'supplier_code' => 'S-1', 'supplier_name' => 'Processor']);
        DB::table('mfg_processing_orders')->insert(['id' => 1, 'order_number' => 'PRC-1', 'order_date' => '2026-09-01', 'supplier_id' => 1, 'process_type' => 'DYEING']);
        DB::table('mfg_fabric_receipts')->insert([
            'receipt_number' => 'FR-1', 'receipt_date' => '2026-09-05', 'fabric_id' => 1,
            'qty_received' => 10, 'qty_rejected' => 2, 'processing_order_id' => 1,
        ]);
        $period = $this->period(ReportPeriod::TYPE_MUTASI_REJECT);
        $line = $period->lines()->firstOrFail();
        $this->assertSame('KGS', $line->satuan_barang);
        $this->assertSame('2.00', $line->jumlah_pemasukan_barang);
        app(InternalReportSyncService::class)->sync($period);
        $this->assertSame(1, $period->lines()->count());
        DB::table('mfg_fabric_receipts')->delete();
        app(InternalReportSyncService::class)->sync($period);
        $this->assertSame(0, $period->lines()->count());
    }

    public function test_shipments_require_physical_stock_out_and_preserve_transaction_values(): void
    {
        $product = \App\Models\Product::create(['sku' => 'FG-1', 'name' => 'Kaos', 'unit' => 'PCS']);
        $invoice = \App\Models\SalesInvoice::create(['invoice_number' => 'INV-1', 'transaction_date' => '2026-09-10', 'contact_name' => 'Buyer']);
        \App\Models\SalesInvoiceDetail::create(['sales_invoice_id' => $invoice->id, 'product_id' => $product->id, 'item_code' => 'FG-1', 'description' => 'Kaos', 'qty_actual' => 4, 'price' => 20, 'amount' => 80]);
        $period = $this->period(ReportPeriod::TYPE_PENGELUARAN);
        $this->assertSame(0, $period->lines()->count());
        DB::table('inventory_ledgers')->insert(['transaction_date' => '2026-09-10', 'evidence_number' => 'INV-1', 'product_id' => $product->id, 'type' => 'OUT', 'qty' => 4, 'unit_cost' => 10, 'total_cost' => 40, 'running_qty' => 0, 'running_value' => 0, 'moving_average_cost' => 10]);
        app(InternalReportSyncService::class)->sync($period);
        $line = $period->lines()->firstOrFail();
        $this->assertSame('4.00', $line->jumlah_barang);
        $this->assertSame('80.0000', $line->nilai);
        $this->assertSame('Buyer', $line->pihak_terkait);
        $this->assertNull($line->tgl_dok_pabean);
        $this->assertSame('', $line->no_pendaftaran_dok_pabean);
        Http::assertNothingSent();
    }

    public function test_wip_uses_actual_cutting_less_rejects_and_completed_stock(): void
    {
        DB::table('mfg_fabrics')->insert(['id' => 1, 'fabric_code' => 'F-WIP', 'fabric_type' => 'Cotton', 'state' => 'FINISHED', 'unit' => 'KGS']);
        DB::table('mfg_work_orders')->insert(['id' => 1, 'spk_number' => 'SPK-WIP', 'order_date' => '2026-09-01']);
        DB::table('mfg_cutting_orders')->insert(['id' => 1, 'cutting_order_number' => 'CO-WIP', 'order_date' => '2026-09-01', 'work_order_id' => 1, 'fabric_id' => 1, 'fabric_qty_issued' => 10, 'planned_pieces' => 100]);
        DB::table('mfg_cutting_checks')->insert(['cutting_order_id' => 1, 'check_date' => '2026-09-05', 'pieces_cut' => 25, 'pieces_ok' => 20, 'pieces_rejected' => 5]);
        DB::table('mfg_stitching_orders')->insert(['id' => 1, 'stitching_order_number' => 'SEW-WIP', 'order_date' => '2026-09-05', 'cutting_order_id' => 1, 'work_order_id' => 1, 'pieces_issued' => 20]);
        DB::table('mfg_finishing_stages')->insert(['stitching_order_id' => 1, 'work_order_id' => 1, 'stage' => 'QC', 'stage_date' => '2026-09-10', 'pieces_in' => 20, 'pieces_ok' => 18, 'pieces_rejected' => 2]);
        $product = \App\Models\Product::create(['sku' => 'WIP-1', 'name' => 'Kaos', 'unit' => 'PCS']);
        DB::table('inventory_ledgers')->insert(['transaction_date' => '2026-09-15', 'evidence_number' => 'SPK-WIP', 'product_id' => $product->id, 'type' => 'IN', 'qty' => 8, 'unit_cost' => 10, 'total_cost' => 80, 'running_qty' => 8, 'running_value' => 80, 'moving_average_cost' => 10]);
        $period = $this->period(ReportPeriod::TYPE_WIP);
        $this->assertSame('10.00', $period->lines()->firstOrFail()->jumlah_barang);
        DB::table('mfg_cutting_checks')->update(['voided_at' => '2026-09-20']);
        app(InternalReportSyncService::class)->sync($period);
        $this->assertSame(0, $period->lines()->count());
    }

    public function test_receipts_include_only_posted_goods_and_remove_voided_transactions(): void
    {
        $company = \App\Modules\Platform\Models\Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $product = \App\Models\Product::create(['sku' => 'RM-1', 'name' => 'Material', 'unit' => 'PCS']);
        $id = DB::table('purchase_receipts')->insertGetId(['company_id' => $company->id, 'receipt_number' => 'GRN-1', 'receipt_date' => '2026-09-05', 'status' => 'DRAFT']);
        DB::table('purchase_receipt_details')->insert(['purchase_receipt_id' => $id, 'product_id' => $product->id, 'item_code' => 'RM-1', 'description' => 'Material', 'qty_received' => 5, 'unit_cost' => 20, 'amount' => 100]);
        $period = $this->period(ReportPeriod::TYPE_PEMASUKAN);
        $this->assertSame(0, $period->lines()->count());
        DB::table('purchase_receipts')->where('id', $id)->update(['status' => 'POSTED']);
        app(InternalReportSyncService::class)->sync($period);
        $line = $period->lines()->firstOrFail();
        $this->assertSame('5.00', $line->jumlah_barang);
        $this->assertSame('100.0000', $line->nilai);
        $this->assertSame('GRN-1', $line->no_bukti);
        DB::table('purchase_receipts')->where('id', $id)->update(['status' => 'VOID']);
        app(InternalReportSyncService::class)->sync($period);
        $this->assertSame(0, $period->lines()->count());
        $this->assertDatabaseCount('purchase_receipt_details', 1);
    }
}