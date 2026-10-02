<?php

namespace App\Modules\Platform\Support;

use App\Models\Account;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use RuntimeException;

class CompanyCoaResolver
{
    public function account(Company $company, string $semanticKey): string
    {
        $definition = CompanyCoaRegistry::manufacturingMgi()[$semanticKey] ?? null;
        if (! $definition) {
            throw new RuntimeException('Semantic key COA tidak dikenal: '.$semanticKey);
        }

        $mapping = CompanyCoaMapping::query()
            ->where('company_id', $company->id)
            ->where('semantic_key', $semanticKey)
            ->where('active', true)
            ->first();
        if (! $mapping) {
            throw new RuntimeException('Mapping COA aktif belum tersedia untuk '.$semanticKey.'.');
        }

        $account = Account::query()->find($mapping->account_code);
        $expectedReport = in_array($semanticKey, ['sales_local', 'sales_export', 'cogs', 'inventory_adjustment', 'auxiliary_material_expense'], true)
            ? 'LABA RUGI' : 'NERACA';
        if (! $account || $account->normal_balance !== $definition['normal_balance']
            || $account->report_pos !== $expectedReport
            || $account->account_code !== $definition['mgi_account']) {
            throw new RuntimeException('Mapping COA tidak valid untuk '.$semanticKey.'.');
        }

        return $account->account_code;
    }
}