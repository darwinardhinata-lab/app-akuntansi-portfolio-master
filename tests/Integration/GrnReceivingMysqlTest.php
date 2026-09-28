<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\GrnScenarios;
use Tests\TestCase;

class GrnReceivingMysqlTest extends TestCase
{
    use GrnScenarios;

    private ?string $fixture = null;
    private bool $created = false;
    private array $fixtureConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MGI_A3_MYSQL_TESTS') !== '1') { $this->markTestSkipped('Run the isolated A3 MySQL verifier.'); }
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1',
            'port' => getenv('MGI_TEST_PORT') ?: '3306', 'username' => getenv('MGI_TEST_USER') ?: 'root',
            'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.a3_admin' => $config]);
        $this->fixture = 'mgi_fresh_a3test_'.bin2hex(random_bytes(8));
        DB::connection('a3_admin')->statement('CREATE DATABASE `'.$this->fixture.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        $config['database'] = $this->fixture;
        $this->fixtureConfig = $config;
        config(['database.connections.a3_fixture' => $config, 'database.default' => 'a3_fixture']);
        DB::setDefaultConnection('a3_fixture');
        // Full migration chain ONLY in the newly generated empty fixture. Never migrate a working database here.
        try {
            $exit = Artisan::call('migrate', ['--database' => 'a3_fixture', '--force' => true]);
            $this->assertSame(0, $exit, Artisan::output());
            $this->seedGrnScenario();
        } catch (\Throwable $e) {
            $this->cleanupFixture();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixture();
        } finally { parent::tearDown(); }
    }

    private function cleanupFixture(): void
    {
        if ($this->created && preg_match('/\Amgi_fresh_a3test_[a-f0-9]{16}\z/', (string) $this->fixture)) {
            DB::purge('a3_fixture');
            DB::connection('a3_admin')->statement('DROP DATABASE `'.$this->fixture.'`');
            $this->created = false;
        }
    }


    public function test_actual_mysql_readiness_rejects_wrong_grn_account_lines(): void
    {
        $this->receive(40);
        $this->artisan('platform:check-grn')->assertExitCode(0);
        DB::table('journal_details')->where('position', 'KREDIT')->update(['account_code' => '22000']);

        $this->artisan('platform:check-grn')->assertExitCode(1);
    }

    public function test_actual_mysql_readiness_reconciles_a_posted_receipt(): void
    {
        $this->receive(40);
        $this->artisan('platform:check-grn')->assertExitCode(0);
        DB::table('purchase_order_details')->where('id', $this->detailId)->update(['qty_received' => 99]);
        $this->artisan('platform:check-grn')->assertExitCode(1);
    }

    public function test_parallel_identical_requests_commit_only_once(): void
    {
        $key = (string) Str::uuid();
        $results = $this->parallelRequests([[100, 'BIL-CONCURRENT', $key], [100, 'BIL-CONCURRENT', $key]]);
        $this->assertSame(0, $results[0]['exit'], $results[0]['output']);
        $this->assertSame(0, $results[1]['exit'], $results[1]['output']);
        $this->assertSame($results[0]['output'], $results[1]['output']);
        $this->assertSame(1, DB::table('purchase_receipts')->count());
        $this->assertSame(1, DB::table('journal_headers')->count());
        $this->assertEquals(100, $this->product->fresh()->stock_quantity);
    }

    public function test_parallel_different_requests_cannot_overreceive(): void
    {
        $results = $this->parallelRequests([[60, 'BIL-RACE-A', (string) Str::uuid()], [60, 'BIL-RACE-B', (string) Str::uuid()]]);
        $exits = array_column($results, 'exit'); sort($exits);
        $this->assertSame([0, 2], $exits);
        $rejected = array_values(array_filter($results, fn ($r) => $r['exit'] === 2));
        $this->assertStringContainsString('jumlah melebihi sisa pesanan', $rejected[0]['output']);
        $this->assertSame(1, DB::table('purchase_receipts')->count());
        $this->assertEquals(60, $this->product->fresh()->stock_quantity);
    }

    private function parallelRequests(array $requests): array
    {
        $code = <<<'CODE'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = json_decode(getenv('GRN_TEST_CONFIG'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/\Amgi_fresh_a3test_[a-f0-9]{16}\z/', $config['database'])) { exit(9); }
config(['platform.grn_inventory_account'=>'114001','platform.grn_payable_account'=>'211001']);
config(['database.connections.a3_fixture'=>$config,'database.default'=>'a3_fixture',
    'platform.grn_inventory_account'=>'114001','platform.grn_payable_account'=>'211001',
'platform.order_company_scope_enabled'=>true,'platform.grn_enabled'=>true,'platform.legacy_sync_enabled'=>false,'customs.enabled'=>false]);
Illuminate\Support\Facades\DB::setDefaultConnection('a3_fixture');
[$po,$detail,$qty,$bill,$key] = json_decode(getenv('GRN_TEST_REQUEST'), true);
try {
    $id = app(App\Services\PurchaseOrderService::class)->receivePartialOrder($po,'2026-09-25',[$detail=>$qty],$bill,null,$key);
    echo 'GRN_ID='.$id;
} catch (RuntimeException $e) { echo $e->getMessage(); exit(2); }
CODE;
        $processes = [];
        try {
            foreach ($requests as [$qty, $bill, $key]) {
                $env = ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/a3-child-'.Str::uuid().'.php',
                    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $this->fixture, 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
                    'GRN_TEST_CONFIG' => json_encode($this->fixtureConfig),
                    'GRN_TEST_REQUEST' => json_encode([$this->po->id, $this->detailId, $qty, $bill, $key])];
                $process = new Process([PHP_BINARY, '-r', $code], base_path(), $env);
                $process->setTimeout(45); $process->start(); $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $results[] = ['exit' => $process->getExitCode(), 'output' => trim($process->getOutput().$process->getErrorOutput())];
            }
            return $results;
        } finally {
            foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(); } }
        }
    }
}
