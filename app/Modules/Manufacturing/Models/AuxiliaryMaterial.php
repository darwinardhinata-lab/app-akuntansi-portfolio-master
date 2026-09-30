<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

class AuxiliaryMaterial extends Model
{
    protected $table = 'mfg_auxiliary_materials';

    protected $fillable = ['material_code', 'hs_code', 'description', 'material_name', 'english_name', 'category', 'color', 'specification', 'meters_per_roll', 'unit', 'stock_quantity', 'average_cost', 'inventory_account_code', 'is_active'];

    protected $casts = ['stock_quantity' => 'decimal:2', 'average_cost' => 'decimal:2', 'meters_per_roll' => 'decimal:2', 'is_active' => 'boolean'];

    public function issues()
    {
        return $this->hasMany(AuxiliaryMaterialIssue::class, 'auxiliary_material_id');
    }
}