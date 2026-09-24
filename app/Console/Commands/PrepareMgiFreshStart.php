<?php

namespace App\Console\Commands;

use App\Modules\Platform\Support\MgiFreshStartPolicy as Policy;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PrepareMgiFreshStart extends Command
{
    protected $signature = 'platform:prepare-mgi
        {target : Database baru, awalan mgi_fresh_}
        {--apply : Membuat database target; default hanya pratinjau}
        {--workers-stopped : Operator sudah menghentikan worker, scheduler dan penulis eksternal}
        {--backup= : Path absolut backup SQL sumber yang sudah diuji restore}
        {--backup-sha256= : SHA-256 backup SQL tersebut}';
    protected $description = 'Prepare an empty MGI database preserving COA/users; NEVER deletes source records or switches .env';

    public function handle(): int
    {
        $source = null;
        $target = null;
        $created = false;
        $snapshot = false;
        try {
            $source = DB::connection();
            if (! in_array($source->getDriverName(), ['mysql', 'mariadb'], true)) {
                throw new RuntimeException('Perintah ini hanya mendukung MySQL/MariaDB. SQLite digunakan hanya untuk unit/feature test kebijakan.');
            }
            $config = $source->getConfig();
            if (! empty($config['prefix']) || isset($config['read']) || isset($config['write'])) {
                throw new RuntimeException('Prefix tabel atau koneksi read/write terpisah harus direview dahulu.');
            }
            $sourceName = (string) $source->selectOne('SELECT DATABASE() AS name')->name;
            $targetName = (string) $this->argument('target');
            Policy::validateTarget($sourceName, $targetName);
            if ($source->selectOne('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$targetName])) {
                throw new RuntimeException('Database target sudah ada. Tidak akan ditimpa, dikosongkan, atau dipakai ulang.');
            }
            $tables = $this->inspect($source, $sourceName);
            $this->info('Sumber: '.$sourceName.' | Target baru: '.$targetName.' | Identitas: '.Policy::NAME);
            $this->table(['Tabel', 'Baris sumber', 'Perlakuan target'], array_map(
                fn ($table) => [$table, $source->table($table)->count(), Policy::mode($table)], array_keys($tables)
            ));
            $this->line('COPY_EXACT: COA/terjemahan, user/password, hak akses, dan migration history.');
            $this->line('USER_REFERENCES_ONLY: hanya divisi yang masih dirujuk users.id_divisi.');
            $this->line('EMPTY: semua transaksi, saldo awal, barang/stok, aset, Party, rekening, jobs, cache, sessions, integrasi dan log lama.');
            if (! $this->option('apply')) {
                $this->info('PRATINJAU SELESAI. Tidak ada perubahan database.');
                return self::SUCCESS;
            }
            if (! app()->isDownForMaintenance() || ! $this->option('workers-stopped')) {
                throw new RuntimeException('Jalankan maintenance dan hentikan semua worker/scheduler/penulis eksternal sebelum --apply.');
            }
            $backup = (string) $this->option('backup');
            $hash = strtolower((string) $this->option('backup-sha256'));
            if (! preg_match('/\A[a-f0-9]{64}\z/', $hash) || ! is_file($backup) || ! is_readable($backup)
                || filesize($backup) === 0 || ! hash_equals($hash, hash_file('sha256', $backup))) {
                throw new RuntimeException('Backup SQL harus tersedia dan SHA-256 harus cocok. Restore-test backup adalah kewajiban operator, bukan dibuktikan oleh hash.');
            }

            // All source reads use one consistent, read-only snapshot. No writes to the source connection after this point.
            $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $source->statement('SET TRANSACTION READ ONLY');
            $source->beginTransaction();
            $snapshot = true;
            $this->assertIdentity($source);

            unset($config['url']);
            $config['database'] = 'information_schema';
            config(['database.connections.mgi_fresh_admin' => $config]);
            DB::purge('mgi_fresh_admin');
            $admin = DB::connection('mgi_fresh_admin');
            $schema = $source->selectOne('SELECT DEFAULT_CHARACTER_SET_NAME AS charset_name, DEFAULT_COLLATION_NAME AS collation_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$sourceName]);
            foreach ([$schema->charset_name, $schema->collation_name] as $identifier) {
                if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier)) {
                    throw new RuntimeException('Identifier charset/collation tidak didukung.');
                }
            }
            $admin->statement('CREATE DATABASE '.$this->quote($targetName).' CHARACTER SET '.$schema->charset_name.' COLLATE '.$schema->collation_name);
            $created = true;
            $config['database'] = $targetName;
            config(['database.connections.mgi_fresh_target' => $config]);
            DB::purge('mgi_fresh_target');
            $target = DB::connection('mgi_fresh_target');
            if ((string) $target->selectOne('SELECT DATABASE() AS name')->name !== $targetName) {
                throw new RuntimeException('Koneksi target tidak sesuai.');
            }

            // DDL is target-only. MySQL DDL auto-commits, so a failed target is left unused, never silently dropped.
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($tables as $table => $ddl) {
                    $target->unprepared($ddl);
                }
            } finally {
                $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
            }

            $target->beginTransaction();
            // Circular references are handled only on target; all FKs are explicitly validated before commit.
            $target->statement('SET SESSION FOREIGN_KEY_CHECKS=0');
            try {
                foreach (array_keys($tables) as $table) {
                    $mode = Policy::mode($table);
                    if ($mode === 'EMPTY') {
                        continue;
                    }
                    $query = $source->table($table);
                    if ($table === 'master_divisi') {
                        $query->whereIn('id_divisi', $source->table('users')->whereNotNull('id_divisi')->select('id_divisi'));
                    }
                    $pk = $this->primaryKey($source, $sourceName, $table);
                    foreach ($pk as $column) {
                        $query->orderBy($column);
                    }
                    $query->chunk(250, function ($rows) use ($target, $table) {
                        $data = [];
                        foreach ($rows as $row) {
                            $row = (array) $row;
                            if ($table === 'company_profiles') {
                                $row = Policy::profile($row);
                            } elseif ($table === 'companies') {
                                $row = Policy::company($row);
                            }
                            $data[] = $row;
                        }
                        if ($data) {
                            $target->table($table)->insert($data);
                        }
                    });
                }
                $summary = $this->verify($source, $target, $sourceName, $tables);
                $target->commit();
            } catch (Throwable $e) {
                $target->rollBack();
                throw $e;
            } finally {
                $target->statement('SET SESSION FOREIGN_KEY_CHECKS=1');
            }
            $source->rollBack();
            $snapshot = false;
            $this->table(['Pemeriksaan', 'Hasil'], $summary);
            $this->info('Database MGI siap diverifikasi operator: '.$targetName);
            $this->warn('BELUM AKTIF: .env tidak diubah. BBW tidak dihapus. Jangan aktifkan scheduler/integrasi legacy pada database MGI.');
            $this->line('Ikuti docs/erp-merge/README_MGI_FRESH_START.md untuk cutover dan pemulihan.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($snapshot && $source && $source->transactionLevel() > 0) {
                $source->rollBack();
            }
            // Do not print SQL bindings: copy errors could contain password hashes or user PII.
            $message = $e instanceof \Illuminate\Database\QueryException
                ? 'Operasi database gagal. Target belum layak digunakan; periksa schema/constraint pada lingkungan administrasi.'
                : $e->getMessage();
            $this->error($message);
            if ($created) {
                $this->warn('Target parsial tetap disimpan untuk diagnosis. Jangan alihkan aplikasi ke target ini. Sumber tidak diubah.');
            }
            return self::FAILURE;
        } finally {
            DB::purge('mgi_fresh_target');
            DB::purge('mgi_fresh_admin');
        }
    }

    private function inspect(Connection $source, string $database): array
    {
        $objects = $source->select('SELECT TABLE_NAME AS name, TABLE_TYPE AS kind, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$database]);
        $tables = [];
        foreach ($objects as $object) {
            if ($object->kind !== 'BASE TABLE' || strtoupper((string) $object->engine) !== 'INNODB') {
                throw new RuntimeException('View/sequence/non-InnoDB ditemukan: '.$object->name.'. Review manual diperlukan.');
            }
            $table = (string) $object->name;
            $ddl = (array) $source->selectOne('SHOW CREATE TABLE '.$this->quote($table));
            $ddl = (string) array_values($ddl)[1];
            if (preg_match('/REFERENCES\s+`[^`]+`\s*\./i', $ddl) || stripos($ddl, ' DATA DIRECTORY') !== false || stripos($ddl, ' TABLESPACE') !== false) {
                throw new RuntimeException('DDL dengan referensi schema/tablespace eksplisit perlu review: '.$table);
            }
            $tables[$table] = $ddl;
        }
        foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $object => $column) {
            if ($source->table('information_schema.'.$object)->where($column, $database)->exists()) {
                throw new RuntimeException('Ada '.$object.' pada source. Tidak disalin otomatis; perlu desain khusus.');
            }
        }
        foreach (array_merge(Policy::EXACT_COPY, ['companies', 'company_profiles', 'master_divisi']) as $required) {
            if (! isset($tables[$required])) {
                throw new RuntimeException('Tabel baseline belum tersedia: '.$required);
            }
            $this->primaryKey($source, $database, $required);
        }
        $accountColumns = $source->getSchemaBuilder()->getColumnListing('accounts');
        if (array_diff($accountColumns, Policy::ACCOUNT_COLUMNS)) {
            throw new RuntimeException('Kolom COA di luar baseline ditemukan. Review kemungkinan saldo tersimpan sebelum menyalin.');
        }
        $this->assertIdentity($source);
        // Reject preserved references that would point to tables whose data are not copied.
        foreach ($this->foreignKeys($source, $database) as $fk) {
            if ($fk->REFERENCED_TABLE_SCHEMA !== $database) {
                throw new RuntimeException('Foreign key lintas database ditemukan; tidak aman dikloning otomatis.');
            }
        }
        return $tables;
    }

    private function assertIdentity(Connection $source): void
    {
        if ($source->table('companies')->count() !== 1 || $source->table('company_profiles')->count() !== 1) {
            throw new RuntimeException('Fresh start hanya untuk sumber satu company/satu profile. Tidak menebak scope multi-company.');
        }
        $company = (array) $source->table('companies')->first();
        $profile = (array) $source->table('company_profiles')->first();
        if ($company['code'] !== 'BBW' || (string) $company['legacy_company_profile_id'] !== (string) $profile['id']) {
            throw new RuntimeException('Company sumber harus BBW dengan profile yang tertaut.');
        }
        Policy::company($company);
        Policy::profile($profile);
        if (! $source->table('users')->exists() || ! $source->table('accounts')->exists()) {
            throw new RuntimeException('Sumber user/COA kosong; hentikan untuk memastikan database yang benar.');
        }
    }

    private function primaryKey(Connection $connection, string $database, string $table): array
    {
        $rows = $connection->select("SELECT COLUMN_NAME AS name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION", [$database, $table]);
        if (! $rows) {
            throw new RuntimeException('Tabel yang dipertahankan wajib memiliki primary key: '.$table);
        }
        return array_map(fn ($row) => $row->name, $rows);
    }

    private function digest(Connection $connection, string $table, array $keys): string
    {
        $query = $connection->table($table);
        foreach ($keys as $key) {
            $query->orderBy($key);
        }
        $hash = hash_init('sha256');
        foreach ($query->cursor() as $row) {
            $row = (array) $row;
            ksort($row);
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
        }
        return hash_final($hash);
    }

    private function verify(Connection $source, Connection $target, string $database, array $tables): array
    {
        $summary = [];
        foreach (Policy::EXACT_COPY as $table) {
            $pk = $this->primaryKey($source, $database, $table);
            if ($this->digest($source, $table, $pk) !== $this->digest($target, $table, $pk)) {
                throw new RuntimeException('Verifikasi data identik gagal: '.$table);
            }
            $summary[] = [$table, 'Identik: '.$target->table($table)->count().' baris'];
        }
        foreach (array_keys($tables) as $table) {
            if (Policy::mode($table) === 'EMPTY' && $target->table($table)->exists()) {
                throw new RuntimeException('Tabel target seharusnya kosong: '.$table);
            }
        }
        $groups = [];
        foreach ($this->foreignKeys($source, $database) as $fk) {
            $groups[$fk->TABLE_NAME.'|'.$fk->CONSTRAINT_NAME][] = $fk;
        }
        foreach ($groups as $columns) {
            $first = $columns[0];
            $notNull = $equal = [];
            foreach ($columns as $column) {
                $notNull[] = 'c.'.$this->quote($column->COLUMN_NAME).' IS NOT NULL';
                $equal[] = 'p.'.$this->quote($column->REFERENCED_COLUMN_NAME).' = c.'.$this->quote($column->COLUMN_NAME);
            }
            $sql = 'SELECT 1 AS invalid FROM '.$this->quote($first->TABLE_NAME).' c WHERE '.implode(' AND ', $notNull)
                .' AND NOT EXISTS (SELECT 1 FROM '.$this->quote($first->REFERENCED_TABLE_NAME).' p WHERE '.implode(' AND ', $equal).') LIMIT 1';
            if ($target->selectOne($sql)) {
                throw new RuntimeException('Foreign key target tidak valid: '.$first->TABLE_NAME.' / '.$first->CONSTRAINT_NAME);
            }
        }
        // Legacy users.id_divisi may not have an actual FK, so validate explicitly.
        if ($target->table('users as u')->whereNotNull('u.id_divisi')->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('master_divisi as d')->whereColumn('d.id_divisi', 'u.id_divisi');
        })->exists()) {
            throw new RuntimeException('Referensi divisi pengguna tidak lengkap.');
        }
        $summary[] = ['Transaksi, saldo awal, master operasional, queue/cache/session', 'Kosong'];
        $summary[] = ['Foreign keys dan referensi divisi user', 'Valid'];
        return $summary;
    }

    private function foreignKeys(Connection $connection, string $database): array
    {
        return $connection->select('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION', [$database]);
    }

    private function quote(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
