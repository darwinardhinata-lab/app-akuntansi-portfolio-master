<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — KnitOrder
 * Tabel: mfg_knit_orders
 */
class KnitOrder extends Model
{
    protected $table = 'mfg_knit_orders';

    protected $fillable = [
        'knit_order_number',
        'order_date',
        'work_order_id',
        'supplier_id',
        'fabric_id',
        'planned_qty_kg',
        'status',
        'target_date',
        'gsm',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'target_date' => 'date',
    ];


    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

    public function yarnIssues()
    {
        return $this->hasMany(YarnIssue::class, 'knit_order_id');
    }

    public function greyFabricReceipts()
    {
        return $this->hasMany(GreyFabricReceipt::class, 'knit_order_id');
    }

}
