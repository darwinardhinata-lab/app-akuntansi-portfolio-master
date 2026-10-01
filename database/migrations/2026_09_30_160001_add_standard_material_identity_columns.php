<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = ['hs_code', 'description', 'material_name', 'english_name', 'category', 'specification', 'meters_per_roll'];

    public function up(): void
    {
        foreach (['mfg_yarns', 'mfg_fabrics'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach ($this->columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) continue;
                    match ($column) {
                        'hs_code' => $table->string('hs_code', 50)->nullable(),
                        'specification' => $table->text('specification')->nullable(),
                        'meters_per_roll' => $table->decimal('meters_per_roll', 20, 2)->nullable(),
                        default => $table->string($column, $column === 'category' ? 100 : 255)->nullable(),
                    };
                }
            });
        }

        if (Schema::hasTable('mfg_auxiliary_materials')) {
            Schema::table('mfg_auxiliary_materials', function (Blueprint $table) {
                foreach (['hs_code', 'description', 'english_name', 'color', 'meters_per_roll'] as $column) {
                    if (Schema::hasColumn('mfg_auxiliary_materials', $column)) continue;
                    match ($column) {
                        'hs_code' => $table->string('hs_code', 50)->nullable(),
                        'color' => $table->string('color', 100)->nullable(),
                        'meters_per_roll' => $table->decimal('meters_per_roll', 20, 2)->nullable(),
                        default => $table->string($column, 255)->nullable(),
                    };
                }
                $table->text('specification')->nullable()->change();
            });
        }

        DB::table('mfg_yarns')->whereNull('description')->update(['description' => DB::raw('yarn_type')]);
        DB::table('mfg_fabrics')->whereNull('description')->update(['description' => DB::raw('fabric_type')]);
        if (Schema::hasTable('mfg_auxiliary_materials')) {
            DB::table('mfg_auxiliary_materials')->whereNull('description')->update(['description' => DB::raw('material_name')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mfg_auxiliary_materials')) {
            Schema::table('mfg_auxiliary_materials', function (Blueprint $table) {
                $table->string('specification', 255)->nullable()->change();
                $table->dropColumn(['hs_code', 'description', 'english_name', 'color', 'meters_per_roll']);
            });
        }
        foreach (['mfg_yarns', 'mfg_fabrics'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn($this->columns);
            });
        }
    }
};