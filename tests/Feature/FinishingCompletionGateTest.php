<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductionLine;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\CuttingOrderService;
use App\Modules\Manufacturing\Services\FinishingStageService;
use App\Modules\Manufacturing\Services\StitchingOrderService;
use App\Modules\Manufacturing\Services\WorkOrderService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinishingCompletionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_finishing_enforces_stage_order_and_quantities_then_completion_uses_packing_qty_and_mgi_coa(): void
    {
        $this->seedCoa();
        [$workOrder, $stitching] = $this->stitchingFlow();
        $finishing = app(FinishingStageService::class);

        try {
            $finishing->record($stitching->id, $this->stage('QC', 10, 10));
            $this->fail('QC tidak boleh melompati Washing dan Ironing.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('IRONING', $exception->getMessage());
        }
        try {
            $finishing->record($stitching->id, $this->stage('WASHING', 11, 10));
            $this->fail('Input finishing tidak boleh melebihi qty Stitching.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('melebihi output tersedia', $exception->getMessage());
        }

        $finishing->record($stitching->id, $this->stage('WASHING', 10, 9, 1));
        $finishing->record($stitching->id, $this->stage('IRONING', 9, 8, 1));
        $finishing->record($stitching->id, $this->stage('QC', 8, 8));
        $finishing->record($stitching->id, $this->stage('PACKING', 8, 8));
        $this->assertSame('COMPLETED', $stitching->fresh()->status);
        $this->assertSame('FINISHING', $workOrder->fresh()->status);

        $product = Product::create(['sku' => 'FG-FINISH', 'name' => 'Garment Finish', 'unit' => 'PCS', 'inventory_account_code' => '114001']);
        try {
            app(WorkOrderService::class)->complete($workOrder->id, $product->id, 7, '2026-09-30');
            $this->fail('Qty completion harus sama dengan packing.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('total hasil PACKING (8 pcs)', $exception->getMessage());
        }

        $completed = app(WorkOrderService::class)->complete($workOrder->id, $product->id, 8, '2026-09-30');
        $this->assertSame('COMPLETED', $completed->status);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $completed->journal_id, 'account_code' => '114001', 'position' => 'DEBET', 'amount' => 600]);
        $this->assertDatabaseHas('journal_details', ['journal_id' => $completed->journal_id, 'account_code' => '114002', 'position' => 'KREDIT', 'amount' => 600]);
    }

    private function stitchingFlow(): array
    {
        $line = ProductionLine::create(['line_code' => 'FINISH-LINE', 'line_name' => 'Finishing Line', 'is_active' => true]);
        $fabric = Fabric::create(['fabric_code' => 'FAB-FINISH', 'fabric_type' => 'Jersey', 'state' => 'FINISHED', 'unit' => 'KGS', 'stock_quantity' => 10, 'average_cost' => 100]);
        $workOrder = WorkOrder::create(['spk_number' => 'MFG-FINISH', 'order_date' => '2026-09-30', 'line_id' => $line->id, 'garment_name' => 'Finish Test', 'planned_qty' => 10]);
        $cutting = app(CuttingOrderService::class)->create($workOrder->id, $fabric->id, ['order_date' => '2026-09-30', 'fabric_qty_issued' => 5, 'planned_pieces' => 10]);
        app(CuttingOrderService::class)->recordCheck($cutting->id, ['check_date' => '2026-09-30', 'pieces_cut' => 10, 'pieces_ok' => 10]);
        $stitching = app(StitchingOrderService::class)->create($cutting->id, $workOrder->id, ['order_date' => '2026-09-30', 'pieces_issued' => 10, 'stitching_rate' => 10]);
        return [$workOrder, $stitching];
    }

    private function stage(string $stage, int $in, int $ok, int $rejected = 0): array
    {
        return ['stage' => $stage, 'stage_date' => '2026-09-30', 'pieces_in' => $in, 'pieces_ok' => $ok, 'pieces_rejected' => $rejected];
    }

    private function seedCoa(): void
    {
        config(['platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        foreach ([['114001', 'Barang jadi', 'DEBET'], ['114002', 'WIP', 'DEBET'], ['114003', 'Bahan baku', 'DEBET'], ['114005', 'Scrap', 'DEBET'], ['510004', 'Penyesuaian', 'DEBET'], ['212001', 'Akrual CMT', 'KREDIT']] as [$code, $name, $balance]) {
            Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => 'Test', 'normal_balance' => $balance, 'report_pos' => 'NERACA']);
        }
        foreach (['finished_goods_inventory' => '114001', 'wip_inventory' => '114002', 'raw_material_inventory' => '114003', 'scrap_inventory' => '114005', 'inventory_adjustment' => '510004', 'subcontract_accrual' => '212001'] as $key => $account) {
            CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $account, 'active' => true]);
        }
    }
}