<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ManualJournalBankCapacityMysqlTest extends \Tests\Feature\ManualJournalBankCapacityTest
{
    private ?string $isolatedDatabase = null;

    public function refreshDatabase(): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MariaDB test.');
        $this->isolatedDatabase = 'audit_manual_writer_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => 'root', 'password' => '', 'database' => 'information_schema', 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.writer_admin' => $config]);
        DB::connection('writer_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database'] = $this->isolatedDatabase;
        config(['database.connections.writer_test' => $config, 'database.default' => 'writer_test']);
        DB::setDefaultConnection('writer_test');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'writer_test', '--force' => true]), Artisan::output());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedDatabase && preg_match('/\Aaudit_manual_writer_[a-f0-9]{16}\z/', $this->isolatedDatabase)) {
                while (DB::connection('writer_test')->transactionLevel() > 0) DB::connection('writer_test')->rollBack();
                DB::purge('writer_test');
                DB::connection('writer_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
            }
        } finally { parent::tearDown(); }
    }
}