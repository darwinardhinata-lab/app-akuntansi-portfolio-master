<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — FabricIssue
 * Tabel: mfg_fabric_issues
 */
class FabricIssue extends Model
{
    protected $table = 'mfg_fabric_issues';

    protected $fillable = [
        'issue_number',
        'issue_date',
        'processing_order_id',
        'fabric_id',
        'qty_issued',
        'unit_cost',
        'total_cost',
        'lot_number',
        'color',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];


    public function processingOrder()
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

}
