<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Imports\FabricImport;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Services\KnitOrderService;
use App\Modules\Manufacturing\Services\ProcessingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FabricCoaSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_requires_explicit_account_without_creating_master(): void
    {
        try {
            (new FabricImport)->collection(new Collection([['CODE', 'Fabric']]));
            $this->fail('Missing mapping accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('inventory_account_code', $e->errors());
        }
        $this->assertDatabaseCount('mfg_fabrics', 0);
    }

    public function test_import_preserves_stock_account_and_rolls_back_previous_rows(): void
    {
        Account::create(['account_code' => '114008', 'account_name' => 'Setengah jadi', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Persediaan']);
        Fabric::create(['fabric_code' => 'OLD', 'fabric_type' => 'Fabric', 'state' => 'GREY', 'unit' => 'KGS', 'inventory_account_code' => '114003', 'stock_quantity' => 2]);
        try {
            (new FabricImport('UTF-8', '114008'))->collection(new Collection([
                ['NEW', 'New fabric', '', 'GREY'], ['OLD', 'Old fabric', '', 'GREY'],
            ]));
            $this->fail('Stock account changed');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('reklasifikasi', $e->getMessage());
        }
        $this->assertDatabaseMissing('mfg_fabrics', ['fabric_code' => 'NEW']);
        $this->assertDatabaseHas('mfg_fabrics', ['fabric_code' => 'OLD', 'inventory_account_code' => '114003', 'stock_quantity' => 2]);
    }

    public function test_unmigrated_maklun_receipts_fail_before_stock_or_journal_changes(): void
    {
        foreach ([KnitOrderService::class => 'receiveGreyFabric',
            ProcessingOrderService::class => 'receiveFabric'] as $service => $method) {
            try {
                app($service)->$method(999, []);
                $this->fail('Maklun without snapshot accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('snapshot COA', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('mfg_fabrics', 0);
    }
}
