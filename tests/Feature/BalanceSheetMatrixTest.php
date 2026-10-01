<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route Customs hanya diregistrasikan ketika modulnya diaktifkan.
        config(['customs.enabled' => false]);
    }

    public function test_authenticated_user_can_open_the_monthly_balance_sheet_matrix(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('balance-sheet.matrix', ['year' => 2026]))
            ->assertOk()
            ->assertViewIs('report.balance-sheet-matrix')
            ->assertViewHas('year', 2026)
            ->assertViewHas('interval', 'bulanan')
            ->assertSee('Neraca Matriks (2026)')
            ->assertSee('Tampilan Standar');
    }

    public function test_balance_sheet_matrix_can_be_exported_as_excel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('balance-sheet.matrix', ['year' => 2026, 'export' => 'excel']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel')
            ->assertHeader('content-disposition', 'attachment; filename="Neraca_Matriks_bulanan_2026.xls"');
    }
}