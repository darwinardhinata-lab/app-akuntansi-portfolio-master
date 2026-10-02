<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Models\FabricIssue;
use App\Modules\Manufacturing\Models\YarnIssue;
use App\Modules\Manufacturing\Services\KnitOrderService;
use App\Modules\Manufacturing\Services\ProcessingOrderService;
use App\Support\MaklunIssueGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaklunIssueSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_is_closed_by_default_before_any_changes(): void
    {
        config(['platform.maklun_issue_enabled' => false]);
        foreach ([KnitOrderService::class, ProcessingOrderService::class] as $service) {
            try {
                if ($service === KnitOrderService::class) {
                    app($service)->issueYarn(999, '2026-10-02', [['yarn_id' => 999, 'qty_issued' => 1]]);
                } else {
                    app($service)->issueFabric(999, 999, 1, '2026-10-02');
                }
                $this->fail('Disabled issue accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('nonaktif', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('mfg_yarn_issues', 0);
        $this->assertDatabaseCount('mfg_fabric_issues', 0);
        $this->assertDatabaseCount('mfg_material_ledgers', 0);
    }

    public function test_snapshot_columns_and_models_preserve_explicit_source(): void
    {
        $this->assertTrue(Schema::hasColumn('mfg_yarn_issues', 'source_account_code'));
        $this->assertTrue(Schema::hasColumn('mfg_fabric_issues', 'source_account_code'));
        $this->assertSame('114003', (new YarnIssue(['source_account_code' => '114003']))->source_account_code);
        $this->assertSame('114008', (new FabricIssue(['source_account_code' => '114008']))->source_account_code);
    }

    public function test_source_rejects_closed_status_invalid_quantity_and_legacy_account(): void
    {
        Account::create(['account_code' => '114003', 'account_name' => 'Bahan baku', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Persediaan']);
        $this->assertSame('114003', MaklunIssueGuard::source('OPEN', 1, '114003', true));
        foreach ([['RECEIVED', 1, '114003'], ['OPEN', -1, '114003'], ['OPEN', 0, '114003'], ['OPEN', 1, '11210']] as [$status, $qty, $code]) {
            try {
                MaklunIssueGuard::source($status, $qty, $code, true);
                $this->fail('Invalid source accepted');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('tidak valid', $e->getMessage());
            }
        }
    }
}
