<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — ProcessingOrder
 * Tabel: mfg_processing_orders
 */
class ProcessingOrder extends Model
{
    protected $table = 'mfg_processing_orders';

    protected $fillable = [
        'order_number',
        'order_date',
        'work_order_id',
        'supplier_id',
        'process_type',
        'status',
        'target_date',
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

    public function fabricIssues()
    {
        return $this->hasMany(FabricIssue::class, 'processing_order_id');
    }

    public function fabricReceipts()
    {
        return $this->hasMany(FabricReceipt::class, 'processing_order_id');
    }

}
