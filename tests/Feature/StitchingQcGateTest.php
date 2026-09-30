<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalHeader;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\CuttingOrderService;
use App\Modules\Manufacturing\Services\StitchingOrderService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StitchingQcGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_stitching_requires_active_qc_and_cannot_exceed_qc_accepted_pieces(): void
    {
        $this->seedCoa();
        [$workOrder, $cuttingOrder] = $this->cuttingOrder('MFG-STITCH-GATE');

        $this->expectExceptionMessage('belum di-QC');
        app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(1));
    }

    public function test_stitching_uses_mgi_coa_and_allows_only_remaining_accepted_qc_pieces(): void
    {
        $this->seedCoa();
        [$workOrder, $cuttingOrder] = $this->cuttingOrder('MFG-STITCH-QTY');
        app(CuttingOrderService::class)->recordCheck($cuttingOrder->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 8, 'pieces_rejected' => 2]);

        $first = app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(5));
        $journalId = JournalHeader::where('evidence_number', $first->stitching_order_number)->value('journal_id');
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journalId, 'account_code' => '114002', 'position' => 'DEBET', 'amount' => 50]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $journalId, 'account_code' => '212001', 'position' => 'KREDIT', 'amount' => 50]);

        try {
            app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(4));
            $this->fail('Qty stitching yang melebihi sisa QC harus ditolak.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('melebihi sisa hasil QC', $exception->getMessage());
        }

        app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(3));
        $this->assertSame(8, (int) $cuttingOrder->stitchingOrders()->sum('pieces_issued'));
    }

    public function test_stitching_rejects_cutting_order_from_another_work_order(): void
    {
        $this->seedCoa();
        [$workOrder, $cuttingOrder] = $this->cuttingOrder('MFG-STITCH-OWNER');
        app(CuttingOrderService::class)->recordCheck($cuttingOrder->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10]);
        $otherWorkOrder = WorkOrder::create(['spk_number' => 'MFG-STITCH-OTHER', 'order_date' => '2026-09-30', 'line_id' => $workOrder->line_id, 'garment_name' => 'Other', 'planned_qty' => 10]);

        $this->expectExceptionMessage('SPK yang sama');
        app(StitchingOrderService::class)->create($cuttingOrder->id, $otherWorkOrder->id, $this->stitchingData(1));
    }

    public function test_void_stitching_keeps_source_journal_posts_reversal_and_releases_qc_pieces(): void
    {
        $this->seedCoa();
        [$workOrder, $cuttingOrder] = $this->cuttingOrder('MFG-STITCH-VOID');
        app(CuttingOrderService::class)->recordCheck($cuttingOrder->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 5]);
        $stitching = app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(5));

        app(StitchingOrderService::class)->void($stitching->id);

        $stitching->refresh();
        $this->assertSame('CANCELED', $stitching->status);
        $this->assertNotNull($stitching->voided_at);
        $this->assertNotNull($stitching->reversal_journal_id);
        $this->assertDatabaseHas('journal_headers', ['journal_id' => $stitching->journal_id, 'transaction_type' => 'Stitching Order (MFG)']);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $stitching->reversal_journal_id, 'account_code' => '212001', 'position' => 'DEBET', 'amount' => 50]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $stitching->reversal_journal_id, 'account_code' => '114002', 'position' => 'KREDIT', 'amount' => 50]);
        $this->assertSame('CUTTING', $workOrder->fresh()->status);

        $replacement = app(StitchingOrderService::class)->create($cuttingOrder->id, $workOrder->id, $this->stitchingData(5));
        $this->assertNotSame($stitching->id, $replacement->id);
    }

    private function cuttingOrder(string $spkNumber): array
    {
        $line = ProductionLine::create(['line_code' => $spkNumber, 'line_name' => $spkNumber, 'is_active' => true]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-'.$spkNumber, 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 10, 'average_cost' => 100]);
        $workOrder = WorkOrder::create(['spk_number' => $spkNumber, 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Stitching Test', 'planned_qty' => 10]);
        $cuttingOrder = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10]);

        return [$workOrder, $cuttingOrder];
    }

    private function stitchingData(int $pieces): array
    {
        return ['order_date' => '2026-09-30', 'pieces_issued' => $pieces, 'stitching_rate' => 10];
    }

    private function seedCoa(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        foreach ([['114002', 'Barang dalam proses', 'ASSET', 'DEBET'], ['114003', 'Bahan baku', 'ASSET', 'DEBET'], ['114005', 'Scrap-Afal', 'ASSET', 'DEBET'], ['510004', 'Penyesuaian Persediaan', 'COGS', 'DEBET'], ['212001', 'Akrual subcontractor', 'LIABILITY', 'KREDIT']] as [$code, $name, $type, $balance]) {
            Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => $type, 'normal_balance' => $balance, 'report_pos' => $balance === 'DEBET' ? 'NERACA' : 'NERACA']);
        }
        foreach (['wip_inventory' => '114002', 'raw_material_inventory' => '114003', 'scrap_inventory' => '114005', 'inventory_adjustment' => '510004', 'subcontract_accrual' => '212001'] as $key => $account) {
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $account, 'active' => true]);
        }
    }
}