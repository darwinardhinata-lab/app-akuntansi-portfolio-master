<?php

namespace App\Console\Commands;

use App\Modules\Platform\Models\CompanyCoaMapping;
use App\Modules\Platform\Support\CompanyCoaRegistry;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckCoaMapping extends Command
{
    protected $signature = 'platform:check-coa-mapping';
    protected $description = 'Read-only validation of semantic COA mappings for the sole MGI company';

    public function handle(): int
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                || ! str_starts_with((string) DB::selectOne('SELECT DATABASE() AS db')->db, 'mgi_fresh_')) {
                throw new \RuntimeException('Gunakan database MySQL hasil fresh start MGI.');
            }
            $company = app(OperationalCompany::class)->company();
            if (! Schema::hasTable('company_coa_mappings')) {
                throw new \RuntimeException('Migration company_coa_mappings belum terpasang.');
            }

            $mappings = CompanyCoaMapping::query()->where('company_id', $company->id)->get()->keyBy('semantic_key');
            $accounts = DB::table('accounts')->get()->keyBy('account_code');
            $rows = [];
            $invalid = false;
            foreach (CompanyCoaRegistry::manufacturingMgi() as $key => $definition) {
                $mapping = $mappings->get($key);
                $account = $mapping ? $accounts->get($mapping->account_code) : null;
                $status = ! $mapping ? 'MISSING' : (! $mapping->active ? 'INACTIVE' : (! $account ? 'ACCOUNT_MISSING'
                    : ($account->normal_balance !== $definition['normal_balance'] ? 'NORMAL_BALANCE_INVALID' : 'OK')));
                $rows[] = [$key, $definition['normal_balance'], $mapping?->account_code ?? '-', $account?->account_name ?? '-', $status];
                $invalid = $invalid || $status !== 'OK';
            }
            $this->line('Database: '.DB::connection()->getDatabaseName().' | Company: '.$company->code.' / '.$company->id);
            $this->table(['Semantic key', 'Normal', 'Account', 'Nama akun', 'Status'], $rows);
            if ($invalid) {
                throw new \RuntimeException('COA_MAPPING_CHECK=FAILED; lengkapi/benarkan mapping sebelum posting manufaktur.');
            }
            $this->info('COA_MAPPING_CHECK=PASSED; company='.$company->code.'; keys='.count($rows));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof \Illuminate\Database\QueryException ? 'Query mapping gagal; periksa schema.' : $e->getMessage());
            return self::FAILURE;
        }
    }
}