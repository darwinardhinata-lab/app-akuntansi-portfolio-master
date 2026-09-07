<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — WorkOrder
 * Tabel: mfg_work_orders
 */
class WorkOrder extends Model
{
    protected $table = 'mfg_work_orders';

    protected $fillable = [
        'spk_number',
        'order_date',
        'product_id',
        'style_sku',
        'garment_name',
        'planned_qty',
        'size_breakdown',
        'target_date',
        'status',
        'wip_account_code',
        'total_material_cost',
        'total_process_cost',
        'total_wip_cost',
        'journal_id',
        'created_by',
        'remarks',
    ];

    protected $casts = [
        'size_breakdown' => 'array',
        'order_date' => 'date',
        'target_date' => 'date',
    ];


    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function journal()
    {
        return $this->belongsTo(\App\Models\JournalHeader::class, 'journal_id', 'journal_id');
    }

    public function knitOrders()
    {
        return $this->hasMany(KnitOrder::class, 'work_order_id');
    }

    public function processingOrders()
    {
        return $this->hasMany(ProcessingOrder::class, 'work_order_id');
    }

    public function cuttingOrders()
    {
        return $this->hasMany(CuttingOrder::class, 'work_order_id');
    }

    public function stitchingOrders()
    {
        return $this->hasMany(StitchingOrder::class, 'work_order_id');
    }

    public function finishingStages()
    {
        return $this->hasMany(FinishingStage::class, 'work_order_id');
    }

    public function barcodeLabels()
    {
        return $this->hasMany(BarcodeLabel::class, 'work_order_id');
    }

}
