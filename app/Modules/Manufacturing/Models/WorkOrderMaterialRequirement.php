<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderMaterialRequirement extends Model
{
    protected $table = 'mfg_work_order_material_requirements';

    protected $fillable = ['work_order_id', 'bom_id', 'item_type', 'yarn_id', 'fabric_id', 'auxiliary_material_id', 'item_code', 'item_name', 'unit', 'qty_per_unit', 'waste_percent', 'qty_required', 'unit_cost_snapshot', 'estimated_total_cost', 'remarks'];

    protected $casts = ['qty_per_unit' => 'decimal:6', 'waste_percent' => 'decimal:4', 'qty_required' => 'decimal:6', 'unit_cost_snapshot' => 'decimal:2', 'estimated_total_cost' => 'decimal:2'];
}