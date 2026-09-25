<?php

namespace App\Console\Commands;

use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckOrderOwnership extends Command
{
    protected $signature = 'platform:check-order-ownership';
    protected $description = 'Read-only A2 readiness check; never fills or deletes ownership';

    public function handle(): int
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                throw new \RuntimeException('Readiness operasional harus diperiksa pada MySQL/MariaDB target.');
            }
            $database = (string) DB::selectOne('SELECT DATABASE() AS db')->db;
            if (! str_starts_with($database, 'mgi_fresh_')) {
                throw new \RuntimeException('Koneksi harus database hasil fresh start mgi_fresh_...');
            }
            $company = app(OperationalCompany::class)->company();
            $rows = [];
            $invalid = false;
            foreach (['purchase_orders', 'sales_orders'] as $table) {
                if (! Schema::hasColumn($table, 'company_id')) {
                    throw new \RuntimeException('Migration ownership belum terpasang: '.$table);
                }
                $total = DB::table($table)->count();
                $unowned = DB::table($table)->whereNull('company_id')->count();
                $foreign = DB::table($table)->whereNotNull('company_id')->where('company_id', '!=', $company->id)->count();
                $badParty = DB::table($table.' as o')->whereNotNull('o.party_id')
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('parties as p')
                        ->whereColumn('p.id', 'o.party_id')->whereColumn('p.company_id', 'o.company_id'))->count();
                $rows[] = [$table, $total, $unowned, $foreign, $badParty];
                $invalid = $invalid || ($unowned + $foreign + $badParty > 0);
            }
            $this->line('Database: '.$database.' | Company: '.$company->code.' / '.$company->id);
            $this->table(['Tabel', 'Total', 'Tanpa owner', 'Owner lain', 'Party tidak cocok'], $rows);
            if ($invalid) {
                throw new \RuntimeException('Data perlu review. Tidak ada backfill otomatis; jangan aktifkan scope sebelum semua anomali selesai.');
            }
            $this->info('READINESS=PASSED; scope='. (config('platform.order_company_scope_enabled') ? 'ON' : 'OFF'));
            $this->line('A2 tetap single-company. Invoice/stok/jurnal belum terisolasi multi-company.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e instanceof \Illuminate\Database\QueryException ? 'Readiness query gagal; periksa schema/koneksi.' : $e->getMessage());
            return self::FAILURE;
        }
    }
}
