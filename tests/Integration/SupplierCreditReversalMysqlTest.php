<?php

namespace Tests\Integration;

use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\LegacyFinancialFence;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SupplierCreditReversalMysqlTest extends \Tests\Feature\SupplierCreditReversalTest
{
    private ?string $isolatedDatabase = null;

    public function refreshDatabase(): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MariaDB test.');
        $this->isolatedDatabase = 'audit_credit_void_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.credit_admin' => $config]);
        DB::connection('credit_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database'] = $this->isolatedDatabase;
        config(['database.connections.credit_test' => $config, 'database.default' => 'credit_test']);
        DB::setDefaultConnection('credit_test');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'credit_test', '--force' => true]), Artisan::output());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedDatabase && preg_match('/\Aaudit_credit_void_[a-f0-9]{16}\z/', $this->isolatedDatabase)) {
                while (DB::connection('credit_test')->transactionLevel() > 0) DB::connection('credit_test')->rollBack();
                DB::purge('credit_test');
                DB::purge('credit_observer');
                DB::connection('credit_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
            }
        } finally { parent::tearDown(); }
    }

    public function test_reversal_first_prevents_waiting_approval(): void
    {
        $this->overlap('reverse');
    }

    public function test_approval_first_is_compensated_by_waiting_reversal(): void
    {
        $this->overlap('approve');
    }

    public function test_waiting_reversal_rechecks_invoice_after_payment_commit(): void
    {
        $this->overlap('payment');
    }

    public function test_upgrade_preserves_binding_and_approval_and_rejects_duplicate_reversal(): void
    {
        $f = $this->reversalFixture(true);
        $before = $this->reversalSnapshot($f);
        $name = '2026_11_19_000001_create_supplier_credit_reversals';
        $migration = require database_path('migrations/'.$name.'.php');
        $migration->down();
        DB::table('migrations')->where('migration', $name)->delete();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('supplier_credit_reversals'));
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'credit_test', '--force' => true]), Artisan::output());
        $this->assertEquals($before, $this->reversalSnapshot($f));
        $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk();
        $reversal = (array) DB::table('supplier_credit_reversals')->sole();
        unset($reversal['id']);
        try { DB::table('supplier_credit_reversals')->insert($reversal); $this->fail('Duplicate reversal accepted'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame(1062, $e->errorInfo[1]); }
        $this->assertDatabaseCount('supplier_credit_reversals', 1);
        $this->assertSame('200.50', $this->creditSnapshot($f)['projection']['residual']);
    }

    private function overlap(string $first): void
    {
        $f = $this->reversalFixture();
        $fixture = ['connection' => config('database.connections.credit_test'), 'company' => $f['company']->id,
            'actor' => $first === 'reverse' ? $f['approver']->id : $f['finance']->id,
            'action' => $first === 'reverse' ? 'approve' : 'reverse', 'proposal' => $f['proposal'],
            'bill' => $f['bill'], 'binding' => $f['binding']['id'], 'payload' => $f['reverse_payload']];
        $worker = new Process([PHP_BINARY, base_path('tests/Support/supplier-credit-race-worker.php')], base_path(), [
            'APP_ENV' => 'testing', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'APP_CONFIG_CACHE' => sys_get_temp_dir().'/credit-race-'.bin2hex(random_bytes(8)).'.php',
            'CREDIT_RACE_FIXTURE' => json_encode($fixture, JSON_THROW_ON_ERROR)]);
        $worker->setTimeout(40);
        DB::beginTransaction();
        try {
            LegacyFinancialFence::lock(true);
            if ($first === 'reverse') $this->postJson($f['reverse_url'], $f['reverse_payload'])->assertOk();
            elseif ($first === 'approve') {
                $this->creditActor($f, true);
                app(ApReconciliation::class)->decide($f['proposal'], 1, 'approve', 'Concurrent independent approval');
            } else DB::table('purchase_bills')->where('id', $f['bill'])->update(['payment_status' => 'PAID']);
            $holder = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $worker->start();
            $deadline = microtime(true) + 15;
            $wait = null;
            while ($worker->isRunning() && microtime(true) < $deadline) {
                if (preg_match('/READY:(\d+)/', $worker->getOutput(), $match)) {
                    $wait = DB::connection('credit_admin')->selectOne('SELECT w.requesting_trx_id, w.blocking_trx_id, l.lock_table, l.lock_index
                        FROM information_schema.innodb_lock_waits w JOIN information_schema.innodb_trx t ON t.trx_id = w.requesting_trx_id
                        JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                        JOIN information_schema.innodb_locks l ON l.lock_id = w.requested_lock_id
                        WHERE t.trx_mysql_thread_id = ? AND b.trx_mysql_thread_id = ?', [(int) $match[1], $holder]);
                    if ($wait) break;
                }
                usleep(50000);
            }
            $diagnostics = $wait ? [] : DB::connection('credit_admin')->select('SHOW FULL PROCESSLIST');
            $this->assertNotNull($wait, 'Actual distinct-connection InnoDB wait required: '.$worker->getOutput().$worker->getErrorOutput().json_encode($diagnostics));
            $this->assertStringContainsString('ppl_financial_contexts', $wait->lock_table);
            DB::commit();
            $worker->wait();
            $this->assertSame($first === 'reverse' ? 3 : ($first === 'payment' ? 4 : 0), $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            if ($first === 'reverse') $this->assertStringContainsString('supplier_credit_reversed', $worker->getOutput());
            config(['database.connections.credit_observer' => config('database.connections.credit_test')]);
            $observer = DB::connection('credit_observer');
            $observerId = (int) $observer->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($holder, $observerId);
            $this->assertNotSame((int) $match[1], $observerId);
            $reversal = $observer->table('supplier_credit_reversals')->first();
            $resolution = $observer->table('ppl_ap_reconciliations')->where('id', $f['proposal'])->first();
            if ($first === 'payment') {
                $this->assertNull($reversal);
                $this->assertSame('proposed', $resolution->status);
                $this->assertSame(2, $observer->table('journal_headers')->count());
            } else {
                $this->assertNotNull($reversal);
                $this->assertSame($first === 'approve' ? '20.00' : '0.00', bcadd((string) $reversal->correction_amount, '0', 2));
                $this->assertSame($first === 'approve' ? 'approved' : 'proposed', $resolution->status);
                $this->assertSame(3, $observer->table('journal_headers')->count());
            }
            $old = DB::getDefaultConnection();
            DB::setDefaultConnection('credit_observer');
            try { $projection = $this->creditSnapshot($f)['projection']; }
            finally { DB::setDefaultConnection($old); }
            $this->assertSame('200.50', $projection['residual']);
            $this->assertSame([], $projection['exceptions']);
            if ($path = getenv('CREDIT_RACE_EVIDENCE')) file_put_contents($path, json_encode([
                'first' => $first, 'holder' => $holder, 'worker' => (int) $match[1], 'observer' => $observerId,
                'wait' => $wait, 'worker_exit' => $worker->getExitCode(), 'worker_output' => $worker->getOutput(),
                'reversal' => $reversal, 'resolution' => $resolution, 'projection' => $projection], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
        } finally {
            if ($worker->isRunning()) $worker->stop();
            while (DB::transactionLevel() > 0) DB::rollBack();
        }
    }
}