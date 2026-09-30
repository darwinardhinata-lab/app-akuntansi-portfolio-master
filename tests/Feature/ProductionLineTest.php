<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Modules\Manufacturing\Models\ProductionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_update_deactivate_line_and_assign_it_to_work_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('mfg.production-lines.store'), ['line_code' => 'SEW-01', 'line_name' => 'Sewing Line 01', 'area' => 'Sewing', 'daily_capacity' => 1200, 'is_active' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $line = ProductionLine::sole();
        $this->assertDatabaseHas('mfg_production_lines', ['id' => $line->id, 'line_code' => 'SEW-01', 'is_active' => 1]);

        $product = Product::create(['sku' => 'FG-LINE', 'name' => 'Finished Good']);
        $this->post(route('mfg.work-orders.store'), ['order_date' => '2026-09-30', 'garment_name' => 'Polo Shirt', 'planned_qty' => 100, 'product_id' => $product->id, 'line_id' => $line->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mfg_work_orders', ['line_id' => $line->id, 'garment_name' => 'Polo Shirt']);

        $this->put(route('mfg.production-lines.update', $line->id), ['line_code' => 'SEW-01', 'line_name' => 'Sewing Line Utama', 'area' => 'Sewing', 'daily_capacity' => 1400, 'is_active' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('mfg.production-lines.update', $line->id), ['line_code' => 'SEW-02', 'line_name' => 'Sewing Line Utama', 'is_active' => '1'])
            ->assertSessionHasErrors('line_code');
        $this->delete(route('mfg.production-lines.destroy', $line->id))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mfg_production_lines', ['id' => $line->id, 'is_active' => 0]);
        $this->assertDatabaseHas('mfg_work_orders', ['line_id' => $line->id]);

        $this->post(route('mfg.work-orders.store'), ['order_date' => '2026-09-30', 'garment_name' => 'Rejected Line', 'planned_qty' => 10, 'line_id' => $line->id])
            ->assertSessionHasErrors('line_id');
    }

    public function test_line_code_is_unique_and_master_page_is_available(): void
    {
        $user = User::factory()->create();
        ProductionLine::create(['line_code' => 'CUT-01', 'line_name' => 'Cutting Line 01']);

        $this->actingAs($user)->get(route('mfg.production-lines.index'))->assertOk()->assertSee('Master Line Produksi')->assertSee('CUT-01');
        $this->actingAs($user)->get(route('mfg.work-orders.index'))->assertOk()->assertSee('Line Produksi');
        $this->post(route('mfg.production-lines.store'), ['line_code' => 'CUT-01', 'line_name' => 'Duplicate'])->assertSessionHasErrors('line_code');
    }
}