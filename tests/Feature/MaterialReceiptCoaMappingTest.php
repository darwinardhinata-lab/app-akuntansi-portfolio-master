<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\CompanyCoaMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MaterialReceiptCoaMappingTest extends TestCase
{
    use RefreshDatabase;

    private Yarn $yarn;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'platform.order_company_scope_enabled' => true,
            'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false,
        ]);

        $company = Company::create(['code' => 'MGI', 'name' => 'PT. Magicase Group Indonesia', 'active' => true]);
        $this->account('114003', 'Bahan baku', 'ASSET', 'DEBET');
        $this->account('117008', 'PPN Masukan', 'ASSET', 'DEBET');
        $this->account('211001', 'Utang Usaha', 'LIABILITY', 'KREDIT');

        foreach ([
            'raw_material_inventory' => '114003',
            'input_vat' => '117008',
            'accounts_payable' => '211001',
        ] as $semanticKey => $accountCode) {
            CompanyCoaMapping::create([
                'company_id' => $company->id,
                'semantic_key' => $semanticKey,
                'account_code' => $accountCode,
                'active' => true,
            ]);
        }

        $this->yarn = Yarn::create(['yarn_code' => 'YARN-COA', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $this->supplier = Supplier::create([
            'supplier_code' => 'SUP-COA',
            'supplier_name' => 'Supplier Bahan Baku',
            'supplier_type' => 'RAW_MATERIAL',
        ]);
    }

    public function test_mrn_posts_to_approved_mgi_raw_material_ap_and_input_vat_accounts(): void
    {
        $receipt = app(MaterialReceiptService::class)->createAndPost($this->header(100), [$this->item()]);

        $this->assertDatabaseHas('mfg_material_receipts', ['id' => $receipt->id, 'status' => 'POSTED', 'gross_amount' => 1000, 'tax_amount' => 100]);
        $this->assertEquals(10, $this->yarn->fresh()->stock_quantity);
        $this->assertDatabaseHas('mfg_material_ledgers', ['evidence_number' => $receipt->receipt_number, 'type' => 'IN', 'total_cost' => 1000]);

        $lines = DB::table('journal_details')->where('journal_id', $receipt->journal_id)->orderBy('position')->orderBy('account_code')->get();
        $this->assertSame(['114003', '117008', '211001'], $lines->pluck('account_code')->all());
        $this->assertSame(['DEBET', 'DEBET', 'KREDIT'], $lines->pluck('position')->all());
        $this->assertEquals([1000.0, 100.0, 1100.0], $lines->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
    }

    public function test_mrn_rejects_missing_mapping_without_creating_partial_stock_or_journal(): void
    {
        CompanyCoaMapping::where('semantic_key', 'accounts_payable')->delete();

        try {
            app(MaterialReceiptService::class)->createAndPost($this->header(), [$this->item()]);
            $this->fail('MRN harus ditolak jika mapping accounts_payable tidak tersedia.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Mapping COA aktif belum tersedia untuk accounts_payable', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('mfg_material_receipts')->count());
        $this->assertSame(0, DB::table('mfg_material_ledgers')->count());
        $this->assertSame(0, DB::table('journal_headers')->count());
        $this->assertEquals(0, $this->yarn->fresh()->stock_quantity);
    }

    private function header(float $taxAmount = 0): array
    {
        return [
            'receipt_date' => '2026-09-30',
            'supplier_id' => $this->supplier->id,
            'tax_amount' => $taxAmount,
        ];
    }

    private function item(): array
    {
        return [
            'item_type' => 'YARN',
            'yarn_id' => $this->yarn->id,
            'item_name' => 'Cotton Yarn',
            'qty' => 10,
            'unit' => 'KGS',
            'rate' => 100,
        ];
    }

    private function account(string $code, string $name, string $type, string $normalBalance): void
    {
        Account::create([
            'account_code' => $code,
            'account_name' => $name,
            'coa_type' => $type,
            'normal_balance' => $normalBalance,
            'report_pos' => 'NERACA',
        ]);
    }
}