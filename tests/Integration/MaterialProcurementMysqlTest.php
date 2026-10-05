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

    private function cleanupFixture(): void
    {
        if ($this->created && preg_match('/\Amgi_fresh_m2test_[a-f0-9]{16}\z/', (string) $this->fixture)) {
            DB::purge('m2_fixture');
            DB::connection('m2_admin')->statement('DROP DATABASE `'.$this->fixture.'`');
            $this->created = false;
        }
    }
}
