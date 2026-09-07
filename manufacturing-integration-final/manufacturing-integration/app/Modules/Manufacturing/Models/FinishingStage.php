<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — FinishingStage
 * Tabel: mfg_finishing_stages
 */
class FinishingStage extends Model
{
    protected $table = 'mfg_finishing_stages';

    protected $fillable = [
        'stitching_order_id',
        'work_order_id',
        'stage',
        'stage_date',
        'pieces_in',
        'pieces_ok',
        'pieces_rejected',
        'size_breakdown',
        'operator',
        'remarks',
    ];

    protected $casts = [
        'stage_date' => 'date',
        'size_breakdown' => 'array',
    ];


    public function stitchingOrder()
    {
        return $this->belongsTo(StitchingOrder::class, 'stitching_order_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function barcodeLabels()
    {
        return $this->hasMany(BarcodeLabel::class, 'finishing_stage_id');
    }

}
