<?php

namespace Tests\Feature;

use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialLedgerReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_stock_card_reads_the_recorded_movement_without_mutating_inventory(): void
    {
        $this->actingAs(User::factory()->create());
        $fabric = Fabric::create(['fabric_code' => 'FAB-LEDGER-01', 'fabric_type' => 'Fabric Ledger', 'description' => 'Fabric Ledger', 'unit' => 'METER', 'stock_quantity' => 10, 'average_cost' => 25_000, 'inventory_account_code' => '114003', 'is_active' => true]);
        MaterialLedger::create(['transaction_date' => '2026-09-30', 'evidence_number' => 'MRN-TEST-01', 'item_type' => 'FABRIC', 'item_id' => $fabric->id, 'type' => 'IN', 'qty' => 10, 'unit_cost' => 25_000, 'total_cost' => 250_000, 'running_qty' => 10, 'running_value' => 250_000, 'moving_average_cost' => 25_000, 'description' => 'Penerimaan bahan test']);
        $ledgerCount = MaterialLedger::count();
        $this->assertSame(1, MaterialLedger::where('item_type', 'FABRIC')->where('item_id', $fabric->id)->count());

        $response = $this->get(route('mfg.material-ledger.index', ['item_type' => 'FABRIC', 'item_id' => $fabric->id, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']));

        $response->assertOk()
            ->assertViewHas('selectedMaterial', fn ($material) => $material->is($fabric))
            ->assertViewHas('itemId', $fabric->id)
            ->assertViewHas('ledgers', fn ($ledgers) => $ledgers->count() === 1 && $ledgers->first()->evidence_number === 'MRN-TEST-01');
        $this->assertSame($ledgerCount, MaterialLedger::count());
        $this->assertSame(10.0, (float) $fabric->fresh()->stock_quantity);
        $this->assertSame(25_000.0, (float) $fabric->fresh()->average_cost);
    }
}