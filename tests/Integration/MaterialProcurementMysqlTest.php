<?php

namespace Tests\Integration;

use App\Models\User;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialProcurementMysqlTest extends TestCase
{
    private ?string $fixture = null;

    private bool $created = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MGI_M2_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Run the isolated M2 MySQL verifier.');
        }
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: '3306', 'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.m2_admin' => $config]);
        $this->fixture = 'mgi_fresh_m2test_'.bin2hex(random_bytes(8));
        DB::connection('m2_admin')->statement('CREATE DATABASE `'.$this->fixture.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        $config['database'] = $this->fixture;
        config(['database.connections.m2_fixture' => $config, 'database.default' => 'm2_fixture']);
        DB::setDefaultConnection('m2_fixture');
        try {
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'm2_fixture', '--force' => true]), Artisan::output());
        } catch (\Throwable $e) {
            $this->cleanupFixture();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixture();
        } finally {
            parent::tearDown();
        }
    }

    public function test_full_mysql_migration_chain_supports_pr_to_approved_po_without_posting(): void
    {
        $yarn = Yarn::create(['yarn_code' => 'YARN-M2-MYSQL', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $supplier = Supplier::create(['supplier_code' => 'SUP-M2-MYSQL', 'supplier_name' => 'Supplier Raw Material', 'supplier_type' => 'RAW_MATERIAL']);
        $item = ['item_type' => 'YARN', 'yarn_id' => $yarn->id, 'item_name' => 'Cotton Yarn', 'qty' => 20, 'unit' => 'KGS', 'rate' => 12500];
        $service = app(MaterialProcurementService::class);
        // FIX: izin fixture eksplisit dengan pembuat dan approver terpisah.
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        config([
            'platform.pr_create_user_ids' => [(string) $creator->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
            'platform.po_create_user_ids' => [(string) $creator->id],
            'platform.po_approve_user_ids' => [(string) $approver->id],
        ]);
        $pr = $service->createRequest(['request_date' => '2026-09-28', 'created_by' => $creator->id], [$item]);
        $service->submitRequest($pr->id, $creator->id);
        $service->approveRequest($pr->id, $approver->id);
        // FIX: verifier terisolasi juga memeriksa histori transisi PR pada revisi awal.
        $this->assertSame(0, $pr->fresh()->revision_no);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $pr->histories()->pluck('action')->all());
        $this->assertDatabaseHas('mfg_material_purchase_request_histories', [
            'request_id' => $pr->id, 'action' => 'APPROVED', 'from_status' => 'SUBMITTED',
            'to_status' => 'APPROVED', 'actor_id' => $approver->id, 'revision_no' => 0,
        ]);
        $detail = $pr->fresh('details')->details->sole();
        $po = $service->createOrderFromRequest($pr->id, $supplier->id, [$item + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28', 'created_by' => $creator->id]);
        $service->submitOrder($po->id, $creator->id);
        $service->approveOrder($po->id, $approver->id);
        // FIX: histori PO dan identitas approver diperiksa tanpa melonggarkan SoD fixture.
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $po->histories()->pluck('action')->all());
        $this->assertDatabaseHas('mfg_material_purchase_order_histories', [
            'order_id' => $po->id, 'action' => 'APPROVED', 'from_status' => 'SUBMITTED',
            'to_status' => 'APPROVED', 'actor_id' => $approver->id, 'revision_no' => 0,
        ]);

        $this->assertDatabaseHas('mfg_material_purchase_orders', ['id' => $po->id, 'approval_status' => 'APPROVED', 'fulfillment_status' => 'OPEN']);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id, 'source_request_detail_id' => $detail->id, 'qty' => 20]);
        $this->assertSame(0, DB::table('journal_headers')->count());
        $this->assertSame(0, DB::table('inventory_ledgers')->count());
        $this->assertSame(0, DB::table('mfg_material_ledgers')->count());
    }

    public function test_supplier_identity_authorization_and_failed_second_line_preserve_mysql_state(): void
    {
        $service = app(MaterialProcurementService::class);
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        config([
            'platform.pr_create_user_ids' => [(string) $creator->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
            'platform.po_create_user_ids' => [(string) $creator->id],
        ]);
        $material = \App\Modules\Manufacturing\Models\AuxiliaryMaterial::create([
            'material_code' => '000017', 'material_name' => 'Label', 'unit' => 'PCS',
            'meters_per_roll' => '12.75', 'stock_quantity' => '5.25', 'average_cost' => '1.50',
        ]);
        $supplier = Supplier::create(['supplier_code' => '000017', 'supplier_name' => 'Same name', 'supplier_type' => 'RAW_MATERIAL', 'is_active' => true]);
        $wrong = Supplier::create(['supplier_code' => 'OTHER', 'supplier_name' => 'Same name', 'supplier_type' => 'KNITTER', 'is_active' => true]);
        $item = ['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $material->id,
            'item_name' => 'Label', 'qty' => '2.00', 'unit' => 'PCS', 'rate' => '12.75'];
        $pr = $service->createRequest(['request_date' => '2026-11-17', 'created_by' => $creator->id], [$item]);
        $service->submitRequest($pr->id, $creator->id);
        $service->approveRequest($pr->id, $approver->id);
        $detail = $pr->fresh('details')->details->sole();
        $line = $item + ['source_request_detail_id' => $detail->id];
        $header = ['po_date' => '2026-11-17', 'created_by' => $creator->id];
        $snapshot = function (): array {
            $state = [];
            foreach (DB::select('SHOW TABLES') as $table) {
                $name = array_values((array) $table)[0];
                $rows = DB::table($name)->get()->map(fn ($row) => json_encode($row))->all();
                sort($rows);
                $state[$name] = $rows;
            }
            ksort($state);
            return $state;
        };
        foreach (['wrong_type', 'inactive', 'revoked_permission', 'second_line'] as $case) {
            $supplier->update(['is_active' => $case !== 'inactive']);
            config(['platform.po_create_user_ids' => $case === 'revoked_permission' ? [] : [(string) $creator->id]]);
            $before = $snapshot();
            try {
                $service->createOrderFromRequest($pr->id, $case === 'wrong_type' ? $wrong->id : $supplier->id,
                    $case === 'second_line' ? [$line, $line] : [$line], $header);
                $this->fail('Invalid supplier, revoked permission or exhausted PR must reject the entire PO.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertSame('revoked_permission', $case);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($case === 'second_line' ? 'sisa detail PR' : 'Supplier Material PO', $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), $case.' must not mutate table rows');
        }
        $po = $service->createOrderFromRequest($pr->id, $supplier->id, [$line], $header);
        $this->assertSame($supplier->id, $po->supplier_id);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id,
            'auxiliary_material_id' => $material->id, 'source_request_detail_id' => $detail->id, 'unit' => 'PCS', 'amount' => '25.50']);
        $this->assertSame('2.00', $detail->fresh()->qty_ordered);
        $before = $snapshot();
        try {
            $service->createOrderFromRequest($pr->id, $supplier->id, [$line], $header);
            $this->fail('Retry must not over-order an exhausted source line.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sisa detail PR', $e->getMessage());
        }
        $this->assertSame($before, $snapshot());
        $service->submitOrder($po->id, $creator->id);
        foreach (['revoked_approver', 'self_approval'] as $case) {
            // Even an explicitly listed creator must not approve their own submitted PO.
            config(['platform.po_approve_user_ids' => $case === 'self_approval' ? [(string) $creator->id] : []]);
            $before = $snapshot();
            try {
                $service->approveOrder($po->id, $case === 'self_approval' ? $creator->id : $approver->id);
                $this->fail('Revocation and separation of duties must reject PO approval.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertStringContainsString('Material PO', $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), $case.' must not mutate table rows');
        }
        config(['platform.po_approve_user_ids' => [(string) $approver->id]]);
        // Inject a real MariaDB persistence failure after the header update, not a mocked transaction.
        DB::unprepared("CREATE TRIGGER m3_fail_approval_history BEFORE INSERT ON mfg_material_purchase_order_histories
            FOR EACH ROW BEGIN IF NEW.action = 'APPROVED' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'M3 synthetic approval history failure'; END IF; END");
        $before = $snapshot();
        try {
            try {
                $service->approveOrder($po->id, $approver->id);
                $this->fail('Failed history persistence must roll back the approval header.');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('M3 synthetic approval history failure', $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), 'History failure must restore every table row');
            $this->assertSame('SUBMITTED', $po->fresh()->approval_status);
            $this->assertNull($po->fresh()->approved_by);
            $this->assertNull($po->fresh()->approved_at);
        } finally {
            DB::unprepared('DROP TRIGGER m3_fail_approval_history');
        }
        $approved = $service->approveOrder($po->id, $approver->id);
        $this->assertSame('APPROVED', $approved->approval_status);
        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame($approver->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $approved->histories()->pluck('action')->all());
        $this->assertSame('2.00', $detail->fresh()->qty_ordered);
        $before = $snapshot();
        try {
            $service->approveOrder($po->id, $approver->id);
            $this->fail('Approval retry must not append another history or mutate the approved PO.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('Material PO', $e->getMessage());
        }
        $this->assertSame($before, $snapshot());
        $this->assertSame('000017', $material->fresh()->material_code);
        $this->assertSame('12.75', $material->fresh()->meters_per_roll);
        $this->assertSame('5.25', $material->fresh()->stock_quantity);
        $this->assertSame('1.50', $material->fresh()->average_cost);
        foreach (['journal_headers', 'journal_details', 'inventory_ledgers', 'mfg_material_ledgers'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    private function cleanupFixture(): void
    {
        if ($this->created && preg_match('/\Amgi_fresh_m2test_[a-f0-9]{16}\z/', (string) $this->fixture)) {
            DB::purge('m2_fixture');
            DB::connection('m2_admin')->statement('DROP DATABASE `'.$this->fixture.'`');
            $this->created = false;
        }
    }
}
