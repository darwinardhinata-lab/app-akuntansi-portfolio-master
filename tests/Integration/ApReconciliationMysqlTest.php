<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Runs the actual reconciliation/Livewire regression against a disposable MySQL schema. */
class ApReconciliationMysqlTest extends \Tests\Feature\ApReconciliationTest
{
    private ?string $isolatedDatabase = null;

    public function test_competing_return_approvals_serialize_and_recheck_capacity(): void
    {
        parent::test_return_allocation_across_invoices_requires_full_quantity_and_preserves_journal();
        $detail = DB::table('purchase_return_details')->first();
        DB::table('purchase_return_details')->where('id', $detail->id)->update(['qty_returned' => 5]);
        $original = DB::table('ppl_ap_reconciliations')->where('status', 'approved')->first();
        $proposer = \App\Models\User::findOrFail($original->proposed_by);
        $approver = auth()->user();
        $this->actingAs($proposer);
        $input = ['purchase_bill_id' => $original->purchase_bill_id, 'journal_id' => $original->journal_id,
            'purchase_return_detail_id' => $detail->id, 'quantity' => '2', 'amount' => '20.00',
            'reason' => 'Concurrent capacity verification', 'evidence' => 'INTERNAL-RACE-TEST'];
        $service = new \App\Modules\PaymentPlan\Services\ApReconciliation();
        $first = $service->propose($input);
        $second = $service->propose($input);
        $this->actingAs($approver);
        $code = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=json_decode(getenv('AP_FIXTURE'),true);
if(!preg_match('/\Aaudit_ap_test_[a-f0-9]{16}\z/',$cfg['database']))exit(9);
config(['database.connections.ap_worker'=>$cfg,'database.default'=>'ap_worker',
    'platform.filament_enabled'=>true,'payment_plan_v33.bank_workflow'=>true]);
Illuminate\Support\Facades\DB::setDefaultConnection('ap_worker');
$session=app('session')->driver(); $session->start();
$session->put(App\Modules\Platform\Support\CompanyContext::SESSION_KEY,(int)getenv('AP_COMPANY'));
request()->setLaravelSession($session);
Illuminate\Support\Facades\Auth::loginUsingId((int)getenv('AP_ACTOR'));
request()->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
echo "READY\n"; flush();
try {
    (new App\Modules\PaymentPlan\Services\ApReconciliation())->decide((int)getenv('AP_PROPOSAL'),1,'approve','Concurrent independent verification');
    echo 'ACCEPTED';
} catch(Illuminate\Validation\ValidationException $e) { echo json_encode($e->errors()); exit(3); }
PHP;
        $worker = new \Symfony\Component\Process\Process([PHP_BINARY, '-r', $code], base_path(), [
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/ap-race-'.bin2hex(random_bytes(8)).'.php',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'AP_FIXTURE' => json_encode(config('database.connections.ap_test')), 'AP_ACTOR' => (string) $approver->id,
            'AP_COMPANY' => (string) $original->company_id, 'AP_PROPOSAL' => (string) $second,
        ]);
        $worker->setTimeout(30);
        DB::beginTransaction();
        try {
            \App\Modules\PaymentPlan\Services\LegacyFinancialFence::lock(true);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (!str_contains($worker->getOutput(), 'READY') && $worker->isRunning() && microtime(true) < $deadline) usleep(20000);
            $this->assertStringContainsString('READY', $worker->getOutput(), $worker->getErrorOutput());
            usleep(200000);
            $this->assertTrue($worker->isRunning(), 'Second approval must wait for the financial fence. '.$worker->getOutput().' '.$worker->getErrorOutput());
            $service->decide($first, 1, 'approve', 'First independent verification');
            DB::commit();
            $worker->wait();
            $this->assertSame(3, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            $this->assertStringContainsString('source_overallocated', $worker->getOutput());
            $this->assertSame('approved', DB::table('ppl_ap_reconciliations')->where('id', $first)->value('status'));
            $this->assertSame('proposed', DB::table('ppl_ap_reconciliations')->where('id', $second)->value('status'));
            $this->assertSame('50.00', bcadd('0', (string) DB::table('ppl_ap_reconciliations')->where('status', 'approved')->sum('amount'), 2));
        } finally {
            if ($worker->isRunning()) $worker->stop();
            while (DB::transactionLevel() > 0) DB::rollBack();
        }
    }

    public function refreshDatabase(): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MySQL test.');
        $this->isolatedDatabase = 'audit_ap_test_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.ap_test_admin' => $config]);
        DB::connection('ap_test_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database'] = $this->isolatedDatabase;
        config(['database.connections.ap_test' => $config, 'database.default' => 'ap_test']);
        DB::setDefaultConnection('ap_test');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'ap_test', '--force' => true]), Artisan::output());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedDatabase && preg_match('/\Aaudit_ap_test_[a-f0-9]{16}\z/', $this->isolatedDatabase)) {
                while (DB::connection('ap_test')->transactionLevel() > 0) DB::connection('ap_test')->rollBack();
                DB::purge('ap_test');
                DB::connection('ap_test_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
            }
        } finally {
            parent::tearDown();
        }
    }
}