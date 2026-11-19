<?php

namespace Tests\Integration;

/** Reuse existing random-schema MariaDB lifecycle and run only the targeted date matrix. */
class SupplierCreditReversalDateMysqlTest extends \Tests\Feature\SupplierCreditReversalDateTest
{
    private ?string $isolatedDatabase = null;

    public function refreshDatabase(): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MariaDB test.');
        $this->isolatedDatabase = 'audit_credit_date_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.date_admin' => $config]);
        \Illuminate\Support\Facades\DB::connection('date_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database'] = $this->isolatedDatabase;
        config(['database.connections.date_test' => $config, 'database.default' => 'date_test']);
        \Illuminate\Support\Facades\DB::setDefaultConnection('date_test');
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('migrate', ['--database' => 'date_test', '--force' => true]), \Illuminate\Support\Facades\Artisan::output());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedDatabase && preg_match('/\Aaudit_credit_date_[a-f0-9]{16}\z/', $this->isolatedDatabase)) {
                while (\Illuminate\Support\Facades\DB::connection('date_test')->transactionLevel() > 0) \Illuminate\Support\Facades\DB::connection('date_test')->rollBack();
                \Illuminate\Support\Facades\DB::purge('date_test');
                \Illuminate\Support\Facades\DB::connection('date_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
            }
        } finally { parent::tearDown(); }
    }
}