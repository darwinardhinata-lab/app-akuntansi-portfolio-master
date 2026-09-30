<?php

namespace Tests\Feature;

use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_can_be_created_from_the_master_tax_form_payload(): void
    {
        $this->withoutMiddleware();

        $response = $this->post(route('tax.store'), [
            'tax_code' => 'TEST-PPN',
            'tax_name' => 'Pajak Uji',
            'rate' => 11,
            'tax_type' => 'ADDITION',
            'account_code' => '21101',
            'description' => 'Data pengujian.',
        ]);

        $response->assertRedirect(route('tax.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('taxes', ['tax_code' => 'TEST-PPN', 'tax_type' => 'ADDITION', 'rate' => 11]);
    }

    public function test_empty_tax_master_is_initialized_with_the_indonesia_catalog(): void
    {
        $this->withoutMiddleware();

        $this->get(route('tax.index'))->assertOk();

        $this->assertDatabaseHas('taxes', ['tax_code' => 'PPN-11', 'tax_type' => 'ADDITION', 'rate' => 11]);
        $this->assertDatabaseHas('taxes', ['tax_code' => 'PPH-23-JASA', 'tax_type' => 'DEDUCTION', 'rate' => 2]);
    }

    public function test_catalog_sync_does_not_overwrite_an_existing_tax(): void
    {
        $this->withoutMiddleware();
        Tax::create(['tax_code' => 'PPN-11', 'tax_name' => 'PPN Khusus Perusahaan', 'rate' => 10, 'tax_type' => 'ADDITION']);

        $this->post(route('tax.generate'))->assertRedirect(route('tax.index'));

        $this->assertDatabaseHas('taxes', ['tax_code' => 'PPN-11', 'tax_name' => 'PPN Khusus Perusahaan', 'rate' => 10]);
    }
}
