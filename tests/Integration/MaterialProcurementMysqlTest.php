<?php

namespace Tests\Integration;

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
        if (getenv('MGI_M2_MYSQL_TESTS') !== '1') { $this->markTestSkipped('Run the isolated M2 MySQL verifier.'); }
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
        } catch (\Throwable $e) { $this->cleanupFixture(); throw $e; }
    }

    protected function tearDown(): void
    {
        try { $this->cleanupFixture(); } finally { parent::tearDown(); }
    }

    public function test_full_mysql_migration_chain_supports_pr_to_approved_po_without_posting(): void
    {
        $yarn = Yarn::create(['yarn_code' => 'YARN-M2-MYSQL', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $supplier = Supplier::create(['supplier_code' => 'SUP-M2-MYSQL', 'supplier_name' => 'Supplier Raw Material', 'supplier_type' => 'RAW_MATERIAL']);
        $item = ['item_type' => 'YARN', 'yarn_id' => $yarn->id, 'item_name' => 'Cotton Yarn', 'qty' => 20, 'unit' => 'KGS', 'rate' => 12500];
        $service = app(MaterialProcurementService::class);
        $pr = $service->createRequest(['request_date' => '2026-09-28'], [$item]);
        $service->submitRequest($pr->id, null);
        $service->approveRequest($pr->id, null);
        $detail = $pr->fresh('details')->details->sole();
        $po = $service->createOrderFromRequest($pr->id, $supplier->id, [$item + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28']);
        $service->submitOrder($po->id, null);
        $service->approveOrder($po->id, null);

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