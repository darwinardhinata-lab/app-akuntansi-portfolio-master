<?php

namespace Tests\Integration;

use App\Modules\Platform\Support\MgiFreshStartPolicy;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MgiFreshStartMysqlTest extends TestCase
{
    private ?string $sourceDb = null;
    private ?string $targetDb = null;
    private ?string $backupFile = null;
    private bool $sourceCreated = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MGI_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Set MGI_MYSQL_TESTS=1 with separate MySQL test credentials.');
        }
        $suffix = bin2hex(random_bytes(8));
        $this->sourceDb = 'mgi_test_source_'.$suffix;
        $this->targetDb = 'mgi_fresh_test_'.$suffix;
        $config = [
            'driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1',
            'port' => getenv('MGI_TEST_PORT') ?: '3306',
            'username' => getenv('MGI_TEST_USER') ?: 'root',
            'password' => getenv('MGI_TEST_PASSWORD') ?: '',
            'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true,
        ];
        config(['database.connections.mgi_test_admin' => $config]);
        DB::connection('mgi_test_admin')->statement('CREATE DATABASE `'.$this->sourceDb.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->sourceCreated = true;
        $config['database'] = $this->sourceDb;
        config(['database.connections.mgi_test_source' => $config, 'database.default' => 'mgi_test_source']);
        DB::setDefaultConnection('mgi_test_source');
        $db = DB::connection();
        $schema = $db->getSchemaBuilder();
        $schema->create('company_profiles', function (Blueprint $t) {
            $t->id(); $t->string('company_name'); $t->string('npwp')->nullable(); $t->string('employee_pin')->nullable();
        });
        $schema->create('companies', function (Blueprint $t) {
            $t->id(); $t->string('code'); $t->string('name'); $t->string('base_currency')->default('IDR');
            $t->string('timezone')->default('Asia/Jakarta'); $t->boolean('active')->default(true);
            $t->foreignId('legacy_company_profile_id')->constrained('company_profiles');
        });
        $schema->create('master_divisi', function (Blueprint $t) { $t->id('id_divisi'); $t->string('nama_divisi'); });
        $schema->create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('password'); $t->unsignedBigInteger('id_divisi')->nullable(); });
        $schema->create('accounts', function (Blueprint $t) { $t->string('account_code', 20)->primary(); $t->string('account_name'); });
        foreach (['roles', 'permissions', 'role_permission', 'account_translations', 'coa_type_translations', 'migrations'] as $table) {
            $schema->create($table, function (Blueprint $t) { $t->id(); $t->string('value')->nullable(); });
        }
        $schema->create('user_company', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained('users'); $t->foreignId('company_id')->constrained('companies'); });
        $schema->create('user_role', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->constrained('users'); $t->foreignId('company_id')->constrained('companies'); });
        $schema->create('journal_details', function (Blueprint $t) { $t->id(); $t->string('account_code', 20); $t->foreign('account_code')->references('account_code')->on('accounts'); $t->decimal('amount', 20, 2); });
        $schema->create('products', function (Blueprint $t) { $t->id(); $t->string('name'); });
        $db->table('company_profiles')->insert(['id' => 1, 'company_name' => 'BBW', 'npwp' => 'OLD', 'employee_pin' => '123456']);
        $db->table('companies')->insert(['id' => 1, 'code' => 'BBW', 'name' => 'BBW', 'legacy_company_profile_id' => 1]);
        $db->table('master_divisi')->insert([['id_divisi' => 1, 'nama_divisi' => 'Used'], ['id_divisi' => 2, 'nama_divisi' => 'Unused']]);
        $db->table('users')->insert(['id' => 1, 'name' => 'Operator', 'password' => 'unchanged-test-hash', 'id_divisi' => 1]);
        $db->table('accounts')->insert(['account_code' => '100', 'account_name' => 'Cash']);
        $db->table('user_company')->insert(['user_id' => 1, 'company_id' => 1]);
        $db->table('user_role')->insert(['user_id' => 1, 'company_id' => 1]);
        $db->table('journal_details')->insert(['account_code' => '100', 'amount' => 123]);
        $db->table('products')->insert(['name' => 'Old product']);
        $maintenance = Mockery::mock(MaintenanceMode::class);
        $maintenance->shouldReceive('active')->andReturn(true);
        $this->app->instance(MaintenanceMode::class, $maintenance);
        $this->backupFile = tempnam(sys_get_temp_dir(), 'mgi-test-backup-');
        // Test-only fixture exercises checksum gating; not a claimed restorable database dump.
        file_put_contents($this->backupFile, '-- test backup fixture');
    }

    protected function tearDown(): void
    {
        try {
            if ($this->sourceCreated) {
                DB::purge('mgi_test_source');
                DB::purge('mgi_fresh_target');
                DB::purge('mgi_target_assert');
                // Names are generated by this test, never read from operator configuration.
                foreach ([$this->sourceDb, $this->targetDb] as $database) {
                    if (preg_match('/\Amgi_(test_source|fresh_test)_[a-f0-9]{16}\z/', $database)) {
                        DB::connection('mgi_test_admin')->statement('DROP DATABASE IF EXISTS `'.$database.'`');
                    }
                }
            }
            if ($this->backupFile && is_file($this->backupFile)) { unlink($this->backupFile); }
        } finally {
            parent::tearDown();
        }
    }

    public function test_preview_does_not_create_target_or_change_source(): void
    {
        $this->artisan('platform:prepare-mgi', ['target' => $this->targetDb])->assertExitCode(0);
        $this->assertNull(DB::connection('mgi_test_admin')->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$this->targetDb]));
        $this->assertSame(1, DB::table('journal_details')->count());
    }

    public function test_fresh_database_preserves_accounts_and_users_but_not_transactions(): void
    {
        $this->artisan('platform:prepare-mgi', $this->applyOptions())->assertExitCode(0);
        $config = config('database.connections.mgi_test_source');
        $config['database'] = $this->targetDb;
        config(['database.connections.mgi_target_assert' => $config]);
        $target = DB::connection('mgi_target_assert');
        $this->assertEquals(DB::table('users')->first(), $target->table('users')->first());
        $this->assertEquals(DB::table('accounts')->first(), $target->table('accounts')->first());
        $this->assertSame(0, $target->table('journal_details')->count());
        $this->assertSame(0, $target->table('products')->count());
        $this->assertSame(1, $target->table('master_divisi')->count());
        $this->assertSame(MgiFreshStartPolicy::NAME, $target->table('companies')->value('name'));
        $this->assertNull($target->table('company_profiles')->value('npwp'));
        $this->assertNull($target->table('company_profiles')->value('employee_pin'));
        $this->assertSame(1, DB::table('journal_details')->count());
        $this->assertSame('BBW', DB::table('companies')->value('code'));
        $this->artisan('platform:prepare-mgi', $this->applyOptions())->assertExitCode(1);
    }

    public function test_wrong_backup_hash_does_not_create_target(): void
    {
        $options = $this->applyOptions();
        $options['--backup-sha256'] = str_repeat('0', 64);
        $this->artisan('platform:prepare-mgi', $options)->assertExitCode(1);
        $this->assertNull(DB::connection('mgi_test_admin')->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$this->targetDb]));
    }

    private function applyOptions(): array
    {
        return ['target' => $this->targetDb, '--apply' => true, '--workers-stopped' => true,
            '--backup' => $this->backupFile, '--backup-sha256' => hash_file('sha256', $this->backupFile)];
    }
}
