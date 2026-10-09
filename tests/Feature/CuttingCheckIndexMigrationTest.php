<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CuttingCheckIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supporting_index_repair_is_repeatable_for_fresh_and_existing_databases(): void
    {
        $old = require database_path('migrations/2026_09_30_120003_drop_one_per_order_constraint_from_mfg_cutting_checks_table.php');
        $repair = require database_path('migrations/2026_10_09_100000_ensure_cutting_check_supporting_index.php');
        $this->assertTrue(Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_checks_order_lookup'));
        $old->up();
        $repair->up();
        $repair->down();
        $this->assertTrue(Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_checks_order_lookup'));
        Schema::table('mfg_cutting_checks', fn ($table) => $table->dropIndex('mfg_cutting_checks_order_lookup'));
        $repair->up();
        $repair->up();
        $this->assertTrue(Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_checks_order_lookup'));
        $this->assertFalse(Schema::hasIndex('mfg_cutting_checks', 'mfg_cutting_check_one_per_order'));
    }
}