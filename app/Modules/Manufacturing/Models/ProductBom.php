<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBom extends Model
{
    protected $table = 'mfg_product_boms';

    protected $fillable = ['product_id', 'item_type', 'yarn_id', 'fabric_id', 'auxiliary_material_id', 'qty_per_unit', 'waste_percent', 'remarks', 'is_active'];

    protected $casts = ['qty_per_unit' => 'decimal:6', 'waste_percent' => 'decimal:4', 'is_active' => 'boolean'];

    public function product() { return $this->belongsTo(\App\Models\Product::class); }
    public function yarn() { return $this->belongsTo(Yarn::class); }
    public function fabric() { return $this->belongsTo(Fabric::class); }
    public function auxiliaryMaterial() { return $this->belongsTo(AuxiliaryMaterial::class); }
}