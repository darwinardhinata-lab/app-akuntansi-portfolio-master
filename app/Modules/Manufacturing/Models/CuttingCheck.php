<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — CuttingCheck
 * Tabel: mfg_cutting_checks
 */
class CuttingCheck extends Model
{
    protected $table = 'mfg_cutting_checks';

    protected $fillable = [
        'cutting_order_id',
        'check_date',
        'pieces_cut',
        'pieces_ok',
        'pieces_rejected',
        'fabric_used_kg',
        'fabric_wastage_kg',
        'wastage_cost_amount',
        'size_breakdown_actual',
        'checked_by',
        'remarks',
    ];

    protected $casts = [
        'check_date' => 'date',
        'size_breakdown_actual' => 'array',
    ];


    public function cuttingOrder()
    {
        return $this->belongsTo(CuttingOrder::class, 'cutting_order_id');
    }

}
