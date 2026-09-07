<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — StitchingOrder
 * Tabel: mfg_stitching_orders
 */
class StitchingOrder extends Model
{
    protected $table = 'mfg_stitching_orders';

    protected $fillable = [
        'stitching_order_number',
        'order_date',
        'cutting_order_id',
        'work_order_id',
        'supplier_id',
        'pieces_issued',
        'size_breakdown',
        'target_date',
        'status',
        'stitching_rate',
        'total_stitching_cost',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'target_date' => 'date',
        'size_breakdown' => 'array',
    ];


    public function cuttingOrder()
    {
        return $this->belongsTo(CuttingOrder::class, 'cutting_order_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function finishingStages()
    {
        return $this->hasMany(FinishingStage::class, 'stitching_order_id');
    }

}
