<?php

// FIX: multicurrency — uji command baseline pada SQLite terisolasi tanpa mutasi operasional.
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FxBaselineSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private string $output;

    // FIX: multicurrency — file uji terisolasi dan selalu dibersihkan setelah verifikasi.
    protected function setUp(): void
    {
        parent::setUp();
        $this->output = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fx_baseline_'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->output)) {
            unlink($this->output);
        }
        parent::tearDown();
    }

    // FIX: multicurrency — periksa seluruh agregat dan pastikan hanya SELECT dijalankan.
    public function test_snapshot_records_exact_totals_without_database_mutations(): void
    {
        DB::table('journal_headers')->insert(['journal_id' => 'FX-BASE']);
        DB::table('journal_details')->insert([
            ['journal_id' => 'FX-BASE', 'account_code' => '110001', 'position' => 'DEBET', 'amount' => '0.10'],
            ['journal_id' => 'FX-BASE', 'account_code' => '110001', 'position' => 'DEBET', 'amount' => '0.20'],
            ['journal_id' => 'FX-BASE', 'account_code' => '210001', 'position' => 'KREDIT', 'amount' => '0.30'],
        ]);
        foreach (['purchase_orders' => 'po_number', 'sales_orders' => 'so_number', 'sales_invoices' => 'invoice_number'] as $table => $number) {
            DB::table($table)->insert([
                $number => 'FX-BASE', 'transaction_date' => '2026-10-09',
                'contact_name' => 'Baseline', 'grand_total' => '15800000.25',
            ]);
        }
        DB::table('purchase_bills')->insert([
            'bill_number' => 'FX-BASE', 'bill_date' => '2026-10-09',
            'vendor_name' => 'Baseline', 'grand_total' => '15800000.25',
        ]);
        $tables = ['journal_headers', 'journal_details', 'purchase_orders', 'sales_orders', 'sales_invoices', 'purchase_bills'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->artisan('fx:baseline', ['--out' => $this->output])->assertExitCode(0);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringStartsWith('select ', strtolower(ltrim($query['query'])));
        }

        $snapshot = json_decode(file_get_contents($this->output), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $snapshot['journal_headers_count']);
        $this->assertSame(3, $snapshot['journal_details_count']);
        $this->assertSame(['DEBET' => '0.30', 'KREDIT' => '0.30'], $snapshot['amount_by_position']);
        $this->assertSame([
            ['account_code' => '110001', 'position' => 'DEBET', 'amount' => '0.30'],
            ['account_code' => '210001', 'position' => 'KREDIT', 'amount' => '0.30'],
        ], $snapshot['amount_by_account_position']);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->get()->toJson());
        }
        foreach (array_slice($tables, 2) as $table) {
            $this->assertSame(['count' => 1, 'grand_total' => '15800000.25'], $snapshot['documents'][$table]);
        }
    }

    // FIX: multicurrency — database kosong tetap menghasilkan baseline yang valid.
    public function test_empty_database_has_zero_counts_and_document_totals(): void
    {
        $this->artisan('fx:baseline', ['--out' => $this->output])->assertExitCode(0);
        $snapshot = json_decode(file_get_contents($this->output), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(0, $snapshot['journal_headers_count']);
        $this->assertSame(0, $snapshot['journal_details_count']);
        $this->assertSame([], $snapshot['amount_by_account_position']);
        $this->assertSame([], $snapshot['amount_by_position']);
        foreach ($snapshot['documents'] as $document) {
            $this->assertSame(['count' => 0, 'grand_total' => '0.00'], $document);
        }
    }

    // FIX: multicurrency — guard operasional tidak boleh dilewati meski command read-only.
    public function test_operational_safety_blocks_before_queries_or_file_creation(): void
    {
        $this->app->instance('env', 'local');
        config(['database.connections.sqlite.database' => 'mgi_fresh_protected', 'platform.allow_destructive_database_commands' => false]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->artisan('fx:baseline', ['--out' => $this->output])
                ->expectsOutputToContain('BLOKIR')->assertExitCode(1);
            $this->assertSame([], DB::getQueryLog());
            $this->assertFileDoesNotExist($this->output);
        } finally {
            DB::disableQueryLog();
            $this->app->instance('env', 'testing');
            config(['database.connections.sqlite.database' => ':memory:']);
        }
    }

    // FIX: multicurrency — baseline terdahulu tidak boleh tertimpa dan kegagalan I/O dilaporkan.
    public function test_existing_file_is_not_overwritten(): void
    {
        file_put_contents($this->output, 'snapshot sebelumnya');
        $this->artisan('fx:baseline', ['--out' => $this->output])
            ->expectsOutputToContain('Baseline gagal')->assertExitCode(1);
        $this->assertSame('snapshot sebelumnya', file_get_contents($this->output));
    }

    public function test_missing_output_directory_returns_failure(): void
    {
        $this->artisan('fx:baseline', ['--out' => $this->output.DIRECTORY_SEPARATOR.'snapshot.json'])
            ->expectsOutputToContain('Baseline gagal')->assertExitCode(1);
        $this->assertFileDoesNotExist($this->output);
    }

    // FIX: multicurrency — nama koneksi tidak dikenal tidak boleh fallback ke database default.
    public function test_unknown_connection_is_rejected_and_default_is_preserved(): void
    {
        $default = DB::getDefaultConnection();
        $this->artisan('fx:baseline', ['--database' => 'missing_baseline_connection', '--out' => $this->output])
            ->expectsOutputToContain('tidak terdaftar')->assertExitCode(1);
        $this->assertSame($default, DB::getDefaultConnection());
        $this->assertFileDoesNotExist($this->output);
    }

    // FIX: multicurrency — alias terpilih tidak boleh melewati larangan melalui opt-in atau testing.
    public function test_selected_operational_connection_is_blocked_even_with_opt_in(): void
    {
        config([
            'database.connections.baseline_blocked' => array_merge(config('database.connections.sqlite'), ['database' => 'MGI_FRESH_protected']),
            'platform.allow_destructive_database_commands' => true,
        ]);
        $default = DB::getDefaultConnection();
        $this->artisan('fx:baseline', ['--database' => 'baseline_blocked', '--out' => $this->output])
            ->expectsOutputToContain('BLOKIR')->assertExitCode(1);
        $this->assertSame($default, DB::getDefaultConnection());
        $this->assertFileDoesNotExist($this->output);
    }

    // FIX: multicurrency — semua agregat harus berasal dari koneksi salinan, bukan koneksi default.
    public function test_selected_connection_is_used_for_all_tables_and_restored(): void
    {
        config(['database.connections.baseline_copy' => config('database.connections.sqlite')]);
        $copy = DB::connection('baseline_copy');
        try {
            $copy->statement('CREATE TABLE journal_headers (journal_id TEXT)');
            $copy->statement('CREATE TABLE journal_details (account_code TEXT, position TEXT, amount TEXT)');
            $copy->table('journal_headers')->insert(['journal_id' => 'COPY']);
            $copy->table('journal_details')->insert(['account_code' => '110001', 'position' => 'DEBET', 'amount' => '123.45']);
            foreach (['purchase_orders', 'sales_orders', 'sales_invoices', 'purchase_bills'] as $table) {
                $copy->statement('CREATE TABLE '.$table.' (grand_total TEXT)');
                $copy->table($table)->insert(['grand_total' => '678.90']);
            }
            DB::table('journal_headers')->insert(['journal_id' => 'DEFAULT']);
            $default = DB::getDefaultConnection();
            $this->artisan('fx:baseline', ['--database' => 'baseline_copy', '--out' => $this->output])->assertExitCode(0);
            $this->assertSame($default, DB::getDefaultConnection());
            $snapshot = json_decode(file_get_contents($this->output), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(1, $snapshot['journal_headers_count']);
            $this->assertSame(1, $snapshot['journal_details_count']);
            $this->assertSame(['DEBET' => '123.45'], $snapshot['amount_by_position']);
            $this->assertSame([['account_code' => '110001', 'position' => 'DEBET', 'amount' => '123.45']], $snapshot['amount_by_account_position']);
            foreach ($snapshot['documents'] as $document) {
                $this->assertSame(['count' => 1, 'grand_total' => '678.90'], $document);
            }
            $this->assertSame(0, DB::table('journal_details')->count());
        } finally {
            DB::purge('baseline_copy');
        }
    }
}