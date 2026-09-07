<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — CuttingOrder
 * Tabel: mfg_cutting_orders
 */
class CuttingOrder extends Model
{
    protected $table = 'mfg_cutting_orders';

    protected $fillable = [
        'cutting_order_number',
        'order_date',
        'work_order_id',
        'fabric_id',
        'fabric_qty_issued',
        'fabric_unit_cost',
        'fabric_total_cost',
        'planned_pieces',
        'size_breakdown',
        'marker_efficiency',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'size_breakdown' => 'array',
    ];


    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

    public function checks()
    {
        return $this->hasMany(CuttingCheck::class, 'cutting_order_id');
    }

    public function stitchingOrders()
    {
        return $this->hasMany(StitchingOrder::class, 'cutting_order_id');
    }

}
