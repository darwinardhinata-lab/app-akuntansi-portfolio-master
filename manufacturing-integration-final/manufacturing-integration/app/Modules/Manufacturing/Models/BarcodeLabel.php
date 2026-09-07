<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — BarcodeLabel
 * Tabel: mfg_barcode_labels
 */
class BarcodeLabel extends Model
{
    protected $table = 'mfg_barcode_labels';

    protected $fillable = [
        'work_order_id',
        'finishing_stage_id',
        'product_id',
        'size',
        'barcode',
        'mrp',
        'batch_number',
        'is_printed',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'is_printed' => 'boolean',
    ];


    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function finishingStage()
    {
        return $this->belongsTo(FinishingStage::class, 'finishing_stage_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

}
