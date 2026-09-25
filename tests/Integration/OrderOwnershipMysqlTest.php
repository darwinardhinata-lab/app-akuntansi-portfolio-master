<?php

namespace Tests\Integration;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Runs only against an ephemeral database whose name is generated inside this test. */
class OrderOwnershipMysqlTest extends TestCase
{
    private ?string $fixture = null;
    private bool $created = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MGI_A2_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set MGI_A2_MYSQL_TESTS=1 using the A2 MySQL verifier.');
        }
        $config = [
            'driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1',
            'port' => getenv('MGI_TEST_PORT') ?: '3306',
            'username' => getenv('MGI_TEST_USER') ?: 'root',
            'password' => getenv('MGI_TEST_PASSWORD') ?: '',
            'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true,
        ];
        config(['database.connections.a2_test_admin' => $config]);
        $this->fixture = 'mgi_fresh_a2test_'.bin2hex(random_bytes(8));
        DB::connection('a2_test_admin')->statement('CREATE DATABASE `'.$this->fixture.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        $config['database'] = $this->fixture;
        config(['database.connections.a2_fixture' => $config, 'database.default' => 'a2_fixture',
            'platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        DB::setDefaultConnection('a2_fixture');
        $schema = DB::connection()->getSchemaBuilder();
        $schema->create('companies', function (Blueprint $t) {
            $t->id(); $t->string('code'); $t->string('name'); $t->boolean('active')->default(true); $t->timestamps();
        });
        $schema->create('parties', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); });
        foreach (['purchase_orders' => 'po_number', 'sales_orders' => 'so_number'] as $table => $number) {
            $schema->create($table, function (Blueprint $t) use ($number) {
                $t->id(); $t->string($number)->unique(); $t->date('transaction_date');
                $t->string('contact_name'); $t->unsignedBigInteger('party_id')->nullable(); $t->timestamps();
            });
        }
        foreach ($this->migrationPaths() as $path) { (require $path)->up(); }
        Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->created && preg_match('/\Amgi_fresh_a2test_[a-f0-9]{16}\z/', (string) $this->fixture)) {
                DB::purge('a2_fixture');
                DB::connection('a2_test_admin')->statement('DROP DATABASE `'.$this->fixture.'`');
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_actual_migrations_and_model_ownership_pass_readiness(): void
    {
        $companyId = Company::value('id');
        foreach ([PurchaseOrder::class => 'po_number', SalesOrder::class => 'so_number'] as $class => $number) {
            $order = $class::create([$number => 'A2-001', 'transaction_date' => '2026-09-24', 'contact_name' => 'Test']);
            $this->assertEquals($companyId, $order->fresh()->company_id);
        }
        $this->artisan('platform:check-order-ownership')->assertExitCode(0);
        $this->assertSame(1, DB::table('purchase_orders')->count());
        $this->assertSame(1, DB::table('sales_orders')->count());
    }

    public function test_readiness_detects_unowned_rows_without_backfill_or_deletion(): void
    {
        DB::table('purchase_orders')->insert(['po_number' => 'OLD', 'transaction_date' => '2026-09-24', 'contact_name' => 'Old']);
        $this->artisan('platform:check-order-ownership')->assertExitCode(1);
        $this->assertSame(1, DB::table('purchase_orders')->whereNull('company_id')->count());
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_foreign_key_prevents_deleting_document_owner(): void
    {
        PurchaseOrder::create(['po_number' => 'PO-OWNER', 'transaction_date' => '2026-09-24', 'contact_name' => 'Test']);
        try {
            Company::query()->delete();
            $this->fail('Company deletion must be restricted by foreign key.');
        } catch (QueryException $e) {
            $this->assertSame(1, Company::count());
            $this->assertSame(1, DB::table('purchase_orders')->count());
        }
    }

    public function test_readiness_detects_mismatched_party_and_migrations_are_reversible(): void
    {
        $companyId = Company::value('id');
        $partyId = DB::table('parties')->insertGetId(['company_id' => $companyId + 99]);
        DB::table('sales_orders')->insert(['so_number' => 'BAD-PARTY', 'transaction_date' => '2026-09-24',
            'contact_name' => 'Test', 'company_id' => $companyId, 'party_id' => $partyId]);
        $this->artisan('platform:check-order-ownership')->assertExitCode(1);
        $this->assertSame(1, DB::table('sales_orders')->count());
        // Reversibility is checked ONLY in this disposable fixture, never recommended after real orders exist.
        foreach (array_reverse($this->migrationPaths()) as $path) { (require $path)->down(); }
        $this->assertFalse(DB::connection()->getSchemaBuilder()->hasColumn('sales_orders', 'company_id'));
        $this->assertSame(1, DB::table('sales_orders')->count());
    }

    private function migrationPaths(): array
    {
        return [database_path('migrations/2026_09_24_150001_add_company_id_to_purchase_orders.php'),
            database_path('migrations/2026_09_24_150002_add_company_id_to_sales_orders.php')];
    }
}
