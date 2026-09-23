<?php

namespace Tests\Feature\CustomsReports;

use App\Models\Product;
use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Services\ReportPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoPopulateMutasiTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $type, string $status = ReportPeriod::STATUS_DRAFT): ReportPeriod
    {
        return ReportPeriod::create(['report_type' => $type, 'periode_bulan' => 9, 'periode_tahun' => 2026, 'status' => $status]);
    }

    private function materialLedger(array $values): void
    {
        DB::table('mfg_material_ledgers')->insert(array_merge([
            'transaction_date' => '2026-09-01', 'evidence_number' => 'ML-1', 'item_type' => 'YARN', 'item_id' => 1,
            'type' => 'IN', 'qty' => 0, 'unit_cost' => 0, 'total_cost' => 0, 'running_qty' => 0,
            'running_value' => 0, 'moving_average_cost' => 0, 'created_at' => now(), 'updated_at' => now(),
        ], $values));
    }

    public function test_populate_mutasi_bahan_baku_calculates_saldo_correctly(): void
    {
        DB::table('mfg_yarns')->insert(['yarn_code' => 'YR-001', 'yarn_type' => 'Cotton', 'color' => 'White', 'unit' => 'KGS', 'created_at' => now(), 'updated_at' => now()]);
        $this->materialLedger(['transaction_date' => '2026-08-31', 'evidence_number' => 'ML-OPEN', 'running_qty' => 100]);
        $this->materialLedger(['evidence_number' => 'ML-IN', 'type' => 'IN', 'qty' => 50, 'running_qty' => 150]);
        $this->materialLedger(['transaction_date' => '2026-09-10', 'evidence_number' => 'ML-OUT', 'type' => 'OUT', 'qty' => 20, 'running_qty' => 130]);
        $this->materialLedger(['transaction_date' => '2026-09-15', 'evidence_number' => 'ML-ADJ', 'type' => 'ADJ', 'qty' => -5, 'running_qty' => 125]);

        app(ReportPeriodService::class)->populateMutasiBahanBaku($this->period(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU));

        $line = MutasiLine::firstOrFail();
        $this->assertSame('YR-001', $line->kode_barang);
        $this->assertSame('100.00', $line->saldo_awal);
        $this->assertSame('50.00', $line->jumlah_pemasukan_barang);
        $this->assertSame('20.00', $line->jumlah_pengeluaran_barang);
        $this->assertSame('-5.00', $line->penyesuaian_adjustment);
        $this->assertSame('125.00', $line->saldo_akhir);
    }

    public function test_populate_mutasi_barang_jadi_only_includes_wo_products(): void
    {
        $included = Product::create(['sku' => 'FG-001', 'name' => 'Kaos', 'unit' => 'PCS']);
        $excluded = Product::create(['sku' => 'FG-002', 'name' => 'Celana', 'unit' => 'PCS']);
        DB::table('mfg_work_orders')->insert(['spk_number' => 'SPK-001', 'order_date' => '2026-09-01', 'product_id' => $included->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$included, $excluded] as $product) {
            DB::table('inventory_ledgers')->insert(['transaction_date' => '2026-09-05', 'evidence_number' => 'IL-' . $product->id, 'product_id' => $product->id, 'type' => 'IN', 'qty' => 10, 'unit_cost' => 0, 'total_cost' => 0, 'running_qty' => 10, 'running_value' => 0, 'moving_average_cost' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }

        app(ReportPeriodService::class)->populateMutasiBarangJadi($this->period(ReportPeriod::TYPE_MUTASI_BARANG_JADI));

        $this->assertDatabaseCount('cbr_mutasi_lines', 1);
        $this->assertDatabaseHas('cbr_mutasi_lines', ['kode_barang' => 'FG-001']);
        $this->assertDatabaseMissing('cbr_mutasi_lines', ['kode_barang' => 'FG-002']);
    }

    public function test_populate_is_read_only_against_manufacturing_tables(): void
    {
        DB::table('mfg_yarns')->insert(['yarn_code' => 'YR-READ', 'yarn_type' => 'Cotton', 'unit' => 'KGS', 'created_at' => now(), 'updated_at' => now()]);
        $this->materialLedger(['evidence_number' => 'ML-READ', 'qty' => 5, 'running_qty' => 5]);
        $product = Product::create(['sku' => 'FG-READ', 'name' => 'Barang Jadi', 'unit' => 'PCS']);
        DB::table('mfg_work_orders')->insert(['spk_number' => 'SPK-READ', 'order_date' => '2026-09-01', 'product_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventory_ledgers')->insert(['transaction_date' => '2026-09-01', 'evidence_number' => 'IL-READ', 'product_id' => $product->id, 'type' => 'IN', 'qty' => 5, 'unit_cost' => 0, 'total_cost' => 0, 'running_qty' => 5, 'running_value' => 0, 'moving_average_cost' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $before = collect(['mfg_material_ledgers', 'inventory_ledgers', 'mfg_work_orders'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);

        $service = app(ReportPeriodService::class);
        $service->populateMutasiBahanBaku($this->period(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU));
        $service->populateMutasiBarangJadi($this->period(ReportPeriod::TYPE_MUTASI_BARANG_JADI));

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} must remain read-only during populate.");
        }
    }

    public function test_reject_assist_data_returns_raw_events_without_aggregation(): void
    {
        DB::table('mfg_fabrics')->insert(['fabric_code' => 'FB-REJECT', 'fabric_type' => 'Cotton', 'state' => 'FINISHED', 'unit' => 'KGS', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_work_orders')->insert(['spk_number' => 'SPK-REJECT', 'order_date' => '2026-09-01', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_cutting_orders')->insert(['cutting_order_number' => 'CO-REJECT', 'order_date' => '2026-09-01', 'work_order_id' => 1, 'fabric_id' => 1, 'fabric_qty_issued' => 1, 'planned_pieces' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_stitching_orders')->insert(['stitching_order_number' => 'SEW-REJECT', 'order_date' => '2026-09-01', 'cutting_order_id' => 1, 'work_order_id' => 1, 'pieces_issued' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_cutting_checks')->insert(['cutting_order_id' => 1, 'check_date' => '2026-09-09', 'pieces_cut' => 10, 'pieces_ok' => 8, 'pieces_rejected' => 2, 'fabric_wastage_kg' => 1.25, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mfg_finishing_stages')->insert(['stitching_order_id' => 1, 'work_order_id' => 1, 'stage' => 'QC', 'stage_date' => '2026-09-10', 'pieces_in' => 10, 'pieces_ok' => 9, 'pieces_rejected' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $data = app(ReportPeriodService::class)->rejectAssistData(9, 2026);

        $this->assertCount(1, $data['cutting']);
        $this->assertSame(2, $data['cutting']->first()->pieces_rejected);
        $this->assertCount(1, $data['finishing']);
        $this->assertSame(1, $data['finishing']->first()->pieces_rejected);
    }

    public function test_populate_blocked_when_period_finalized(): void
    {
        $this->expectException(\RuntimeException::class);
        app(ReportPeriodService::class)->populateMutasiBahanBaku($this->period(ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, ReportPeriod::STATUS_FINAL));
    }
}