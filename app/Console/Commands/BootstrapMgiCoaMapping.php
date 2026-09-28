<?php

namespace App\Console\Commands;

use App\Modules\Platform\Models\CompanyCoaMapping;
use App\Modules\Platform\Support\CompanyCoaRegistry;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapMgiCoaMapping extends Command
{
    protected $signature = 'platform:bootstrap-mgi-coa-mapping {--apply : Persist the approved MGI candidate mappings}';
    protected $description = 'Preview or explicitly bootstrap validated MGI manufacturing semantic COA mappings';

    public function handle(): int
    {
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)
                || ! str_starts_with((string) DB::selectOne('SELECT DATABASE() AS db')->db, 'mgi_fresh_')) {
                throw new \RuntimeException('Gunakan database MySQL hasil fresh start MGI.');
            }
            if (! Schema::hasTable('company_coa_mappings')) {
                throw new \RuntimeException('Migration company_coa_mappings belum terpasang.');
            }
            $company = app(OperationalCompany::class)->company();
            $registry = CompanyCoaRegistry::manufacturingMgi();
            $accounts = DB::table('accounts')->whereIn('account_code', array_column($registry, 'mgi_account'))->get()->keyBy('account_code');
            $rows = [];
            foreach ($registry as $key => $definition) {
                $account = $accounts->get($definition['mgi_account']);
                if (! $account || $account->normal_balance !== $definition['normal_balance']) {
                    throw new \RuntimeException('Kandidat COA MGI tidak valid untuk '.$key.'.');
                }
                $rows[] = [$key, $definition['mgi_account'], $account->account_name, $definition['normal_balance']];
            }
            $this->table(['Semantic key', 'Account', 'Nama akun', 'Normal'], $rows);
            if (! $this->option('apply')) {
                $this->warn('DRY_RUN=ONLY; gunakan --apply hanya setelah backup dan review mapping.');
                return self::SUCCESS;
            }
            DB::transaction(function () use ($company, $registry) {
                foreach ($registry as $key => $definition) {
                    CompanyCoaMapping::updateOrCreate(
                        ['company_id' => $company->id, 'semantic_key' => $key],
                        ['account_code' => $definition['mgi_account'], 'active' => true, 'notes' => 'Approved MGI manufacturing mapping', 'updated_by' => null]
                    );
                }
            });
            $this->info('COA_MAPPING_BOOTSTRAP=APPLIED; company='.$company->code.'; keys='.count($registry));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e instanceof \Illuminate\Database\QueryException ? 'Bootstrap mapping gagal; periksa schema.' : $e->getMessage());
            return self::FAILURE;
        }
    }
}