<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use App\Modules\Platform\Support\CompanyCoaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCoaMappingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
    }

    public function test_resolver_rejects_missing_mapping_without_legacy_fallback(): void
    {
        $this->expectExceptionMessage('Mapping COA aktif belum tersedia');
        app(CompanyCoaResolver::class)->account($this->company, 'raw_material_inventory');
    }

    public function test_resolver_returns_mapped_account_with_required_normal_balance(): void
    {
        $this->account('114003', 'Bahan baku', 'DEBET');
        CompanyCoaMapping::create(['company_id' => $this->company->id, 'semantic_key' => 'raw_material_inventory', 'account_code' => '114003', 'active' => true]);

        $this->assertSame('114003', app(CompanyCoaResolver::class)->account($this->company, 'raw_material_inventory'));
    }

    public function test_resolver_rejects_wrong_normal_balance(): void
    {
        $this->account('211001', 'Utang Usaha', 'KREDIT');
        CompanyCoaMapping::create(['company_id' => $this->company->id, 'semantic_key' => 'raw_material_inventory', 'account_code' => '211001', 'active' => true]);

        $this->expectExceptionMessage('Mapping COA tidak valid');
        app(CompanyCoaResolver::class)->account($this->company, 'raw_material_inventory');
    }

    public function test_resolver_rejects_inactive_mapping(): void
    {
        $this->account('114003', 'Bahan baku', 'DEBET');
        CompanyCoaMapping::create(['company_id' => $this->company->id, 'semantic_key' => 'raw_material_inventory', 'account_code' => '114003', 'active' => false]);

        $this->expectExceptionMessage('Mapping COA aktif belum tersedia');
        app(CompanyCoaResolver::class)->account($this->company, 'raw_material_inventory');
    }

    private function account(string $code, string $name, string $normal): void
    {
        Account::create(['account_code' => $code, 'account_name' => $name, 'coa_type' => $normal === 'DEBET' ? 'ASSET' : 'LIABILITY', 'normal_balance' => $normal, 'report_pos' => 'NERACA']);
    }
}