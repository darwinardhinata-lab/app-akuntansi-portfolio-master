<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountingPeriodMysqlTest extends TestCase
{
    public function test_real_mysql_close_lock_blocks_concurrent_post_and_rechecks_closed_state(): void
    {
        if (getenv('PERIOD_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MySQL test.');
        $name = 'audit_period_test_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.period_admin' => $config]);
        DB::connection('period_admin')->statement('CREATE DATABASE `'.$name.'`');
        $worker = null;
        try {
            $config['database'] = $name;
            config(['database.connections.period_fixture' => $config, 'database.default' => 'period_fixture', 'platform.period_lifecycle_preview_enabled' => true]);
            DB::setDefaultConnection('period_fixture');
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'period_fixture', '--force' => true]), Artisan::output());
            $actor = \App\Models\User::factory()->create(['role' => 'FINANCE']);
            config(['platform.period_close_user_ids' => [$actor->id]]);
            DB::beginTransaction();
            \App\Support\AccountingPeriodGuard::lock();
            $code = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->detectEnvironment(fn()=>'testing');
$cfg=json_decode(getenv('PERIOD_FIXTURE'),true);
if(!preg_match('/\Aaudit_period_test_[a-f0-9]{16}\z/',$cfg['database']))exit(9);
config(['database.connections.period_fixture'=>$cfg,'database.default'=>'period_fixture','platform.period_lifecycle_preview_enabled'=>true]);
Illuminate\Support\Facades\DB::setDefaultConnection('period_fixture');
echo "READY\n"; flush();
try { Illuminate\Support\Facades\DB::transaction(function(){App\Models\JournalHeader::create(['transaction_date'=>'2026-09-15']);}); echo 'ACCEPTED'; }
catch(RuntimeException $e){echo $e->getMessage(); exit(2);}
PHP;
            $worker = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/period-'.bin2hex(random_bytes(8)).'.php', 'PERIOD_FIXTURE' => json_encode($config), 'DB_URL' => '', 'CACHE_STORE' => 'array']);
            $worker->setTimeout(30);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (!str_contains($worker->getOutput(), 'READY') && $worker->isRunning() && microtime(true) < $deadline) usleep(20000);
            $this->assertStringContainsString('READY', $worker->getOutput(), $worker->getErrorOutput());
            usleep(200000);
            $this->assertTrue($worker->isRunning(), 'Posting must wait on the GLOBAL lock.');
            app(\App\Services\AccountingPeriodService::class)->change('2026-09', true, 'Isolated concurrency verification', $actor);
            DB::commit();
            $worker->wait();
            $this->assertSame(2, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            $this->assertStringContainsString('closed', $worker->getOutput());
            $this->assertDatabaseCount('journal_headers', 0);
            $this->assertDatabaseCount('accounting_period_events', 1);
        } finally {
            if ($worker?->isRunning()) $worker->stop();
            while (DB::connection('period_fixture')->transactionLevel() > 0) DB::connection('period_fixture')->rollBack();
            DB::purge('period_fixture');
            if (preg_match('/\Aaudit_period_test_[a-f0-9]{16}\z/', $name)) DB::connection('period_admin')->statement('DROP DATABASE `'.$name.'`');
        }
    }
}