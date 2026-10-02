<?php

namespace Tests\Feature;

use App\Models\JournalDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MgiCoaDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_defaults_use_mgi_and_ambiguous_mappings_have_no_fallback(): void
    {
        $this->assertSame('211001', config('coa.hutang_usaha'));
        $this->assertSame('113101', config('coa.piutang_usaha'));
        $this->assertSame('231001', config('coa.uang_muka_jual'));
        $this->assertSame('117008', config('coa.pajak_masukan'));
        $this->assertNull(config('coa.penjualan'));
        $this->assertNull(config('coa.akum_penyusutan'));
        $this->assertNull(config('coa.persediaan_bahan_baku_kain'));
    }

    public function test_bulk_posting_rejects_missing_mapping_before_insert(): void
    {
        $this->expectExceptionMessage('Mapping COA belum ditetapkan');
        JournalDetail::insert([['account_code' => null, 'amount' => 100, 'position' => 'DEBET']]);
    }
}
