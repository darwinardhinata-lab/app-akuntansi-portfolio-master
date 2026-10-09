<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AccountingPeriodMysqlTest extends TestCase
{
    public static function raceCases(): array
    {
        return ['close first' => ['close'], 'balanced post first' => ['balanced'], 'unbalanced post first' => ['unbalanced']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('raceCases')]
    public function test_real_mysql_period_race(string $scenario): void
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
            if ($scenario !== 'close') {
                $journal = \App\Models\JournalHeader::create(['transaction_date' => '2026-09-15']);
                $journal->details()->create(['account_code' => '111101', 'position' => 'DEBET', 'amount' => 100]);
                if ($scenario === 'balanced') $journal->details()->create(['account_code' => '211001', 'position' => 'KREDIT', 'amount' => 100]);
            }
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
try {
    if(getenv('PERIOD_SCENARIO')==='close') {
        Illuminate\Support\Facades\DB::transaction(function(){App\Models\JournalHeader::create(['transaction_date'=>'2026-09-15']);});
    } else {
        $actor=App\Models\User::findOrFail((int)getenv('PERIOD_ACTOR'));
        config(['platform.period_close_user_ids'=>[$actor->id]]);
        // Deliberately establish an old REPEATABLE READ snapshot before waiting on the poster.
        Illuminate\Support\Facades\DB::transaction(function()use($actor){
            App\Support\ProtectedJournalQuery::table('journal_headers')->count();
            app(App\Services\AccountingPeriodService::class)->change('2026-09',true,'Post-first concurrency verification',$actor);
        });
    }
    echo 'ACCEPTED';
}
catch(Illuminate\Validation\ValidationException $e){echo json_encode($e->errors());exit(3);}
catch(RuntimeException $e){echo $e->getMessage(); exit(2);}
PHP;
            $worker = new Process([PHP_BINARY, '-r', $code], base_path(), ['PERIOD_SCENARIO' => $scenario, 'PERIOD_ACTOR' => (string) $actor->id, 'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/period-'.bin2hex(random_bytes(8)).'.php', 'PERIOD_FIXTURE' => json_encode($config), 'DB_URL' => '', 'CACHE_STORE' => 'array']);
            $worker->setTimeout(30);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (!str_contains($worker->getOutput(), 'READY') && $worker->isRunning() && microtime(true) < $deadline) usleep(20000);
            $this->assertStringContainsString('READY', $worker->getOutput(), $worker->getErrorOutput());
            usleep(200000);
            $this->assertTrue($worker->isRunning(), 'Posting must wait on the GLOBAL lock.');
            if ($scenario === 'close') app(\App\Services\AccountingPeriodService::class)->change('2026-09', true, 'Isolated concurrency verification', $actor);
            DB::commit();
            $worker->wait();
            $this->assertSame($scenario === 'close' ? 2 : ($scenario === 'balanced' ? 0 : 3), $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            $this->assertStringContainsString($scenario === 'close' ? 'closed' : ($scenario === 'balanced' ? 'ACCEPTED' : 'Unbalanced'), $worker->getOutput());
            $this->assertDatabaseCount('journal_headers', $scenario === 'close' ? 0 : 1);
            $this->assertDatabaseCount('accounting_period_events', $scenario === 'unbalanced' ? 0 : 1);
        } finally {
            if ($worker?->isRunning()) $worker->stop();
            while (DB::connection('period_fixture')->transactionLevel() > 0) DB::connection('period_fixture')->rollBack();
            DB::purge('period_fixture');
            if (preg_match('/\Aaudit_period_test_[a-f0-9]{16}\z/', $name)) DB::connection('period_admin')->statement('DROP DATABASE `'.$name.'`');
        }
    }
}