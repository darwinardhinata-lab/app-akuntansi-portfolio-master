<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;

ini_set('display_errors', '0');
ini_set('log_errors', '0');
$stage = 'target';
try {
    $input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $url = parse_url($input['url']);
    $schema = $input['schema'];
    $alias = 'r1_disposable';
    $port = $url['port'] ?? 0;
    if ($input['alias'] !== $alias || ($url['scheme'] ?? '') !== 'mysql' || ($url['host'] ?? '') !== '127.0.0.1'
        || $port <= 1024 || $port === 3306 || isset($url['query']) || isset($url['fragment'])
        || !preg_match('/\Ar1dry[a-f0-9]{16}\z/', $schema) || ($url['path'] ?? '') !== '/'.$schema
        || ($url['user'] ?? '') !== $schema || empty($url['pass']) || empty($input['server'])) {
        throw new RuntimeException();
    }

    $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port, 'database' => $schema,
        'username' => $schema, 'password' => rawurldecode($url['pass']), 'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true, 'options' => [PDO::ATTR_TIMEOUT => 5]];
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    // Pin the catalog before providers boot, even when Laravel loads cached configuration.
    $app->afterBootstrapping(LoadConfiguration::class, static function ($app) use ($alias, $config): void {
        $app['config']->set(['database.connections' => [$alias => $config], 'database.default' => $alias]);
    });
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    if (DB::getDefaultConnection() !== $alias || config('database.connections') !== [$alias => $config]) throw new RuntimeException();
    $db = DB::connection($alias);
    $identity = $db->selectOne('SELECT DATABASE() db_name, CURRENT_USER() principal, VERSION() version, @@hostname server_name, @@port server_port');
    if ($db->getName() !== $alias || $db->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
        || !str_contains($identity->version, 'MariaDB') || $identity->db_name !== $schema || (int) $identity->server_port !== $port
        || $identity->server_name !== $input['server'] || !in_array($identity->principal, [$schema.'@127.0.0.1', $schema.'@localhost'], true)) {
        throw new RuntimeException();
    }
    $schemaGrant = false;
    foreach ($db->select('SHOW GRANTS') as $row) {
        $grant = array_values((array) $row)[0];
        if (preg_match('/\AGRANT USAGE ON \*\.\* TO /', $grant) && !str_contains($grant, 'WITH GRANT OPTION')) continue;
        if (!preg_match('/\AGRANT ALL PRIVILEGES ON `'.preg_quote($schema, '/').'`\.\* TO /', $grant)
            || str_contains($grant, 'WITH GRANT OPTION')) throw new RuntimeException();
        $schemaGrant = true;
    }
    if (!$schemaGrant || $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', $schema)->exists()
        || $db->table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', $schema)->exists()
        || $db->table('information_schema.EVENTS')->where('EVENT_SCHEMA', $schema)->exists()) throw new RuntimeException();
    echo "AC09 target verified: MariaDB loopback:$port/$schema via $alias\n";

    $stage = 'migration';
    $target = '2026_10_29_120000_extend_private_attachments_for_reconciliation';
    $paths = glob(database_path('migrations/*.php'));
    $targetPath = database_path('migrations/'.$target.'.php');
    $before = array_values(array_filter($paths, fn ($path) => strcmp(basename($path), basename($targetPath)) < 0));
    $migrate = static function (array $paths) use ($kernel, $alias): void {
        $output = new Symfony\Component\Console\Output\NullOutput();
        if ($kernel->call('migrate', ['--database' => $alias, '--realpath' => true, '--path' => $paths, '--force' => true], $output) !== 0) {
            throw new RuntimeException();
        }
    };
    $migrate($before);
    $ledger = static fn () => (array) DB::connection($alias)->table('journal_details')->selectRaw(
        'COUNT(*) total, COALESCE(SUM(CASE WHEN position = ? THEN amount ELSE 0 END), 0) debit, COALESCE(SUM(CASE WHEN position = ? THEN amount ELSE 0 END), 0) credit',
        ['DEBET', 'KREDIT'],
    )->first();
    $baseline = $ledger();
    $migrate([$targetPath]);

    $stage = 'verification';
    $builder = $db->getSchemaBuilder();
    $constraint = $db->table('information_schema.CHECK_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', $schema)
        ->where('TABLE_NAME', 'ppl_attachments')->where('CONSTRAINT_NAME', 'ppl_attachments_one_context')->value('CHECK_CLAUSE');
    $columns = array_column($builder->getColumns('ppl_attachments'), null, 'name');
    if (!$db->table('migrations')->where('migration', $target)->exists()
        || !$builder->hasColumns('ppl_attachment_audits', ['attachment_id', 'actor_id', 'action'])
        || !($columns['request_id']['nullable'] ?? false) || !($columns['reconciliation_id']['nullable'] ?? false)
        || !$builder->hasIndex('ppl_attachments', ['request_id', 'kind', 'sha256'], 'unique')
        || !$builder->hasIndex('ppl_attachments', ['reconciliation_id', 'sha256'], 'unique')
        || strtolower(preg_replace('/[\s`()]/', '', (string) $constraint)) !== 'request_idisnull<>reconciliation_idisnull'
        || $ledger() !== $baseline) throw new RuntimeException();
    echo "AC09 PASS: attachment migration, schema and ledger control totals (disposable only)\n";
    exit(0);
} catch (Throwable) {
    // Never print PDO/SQL errors: connection URLs or bound values may contain secrets.
    fwrite(STDERR, "AC09 FAIL: $stage\n");
    exit(match ($stage) {'target' => 2, 'migration' => 3, default => 4});
}