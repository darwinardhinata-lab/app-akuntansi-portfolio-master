<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — YarnIssue
 * Tabel: mfg_yarn_issues
 */
class YarnIssue extends Model
{
    protected $table = 'mfg_yarn_issues';

    protected $fillable = [
        'issue_number',
        'issue_date',
        'knit_order_id',
        'yarn_id',
        'lot_number',
        'qty_issued',
        'unit_cost',
        'total_cost',
        'returned_qty',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];


    public function knitOrder()
    {
        return $this->belongsTo(KnitOrder::class, 'knit_order_id');
    }

    public function yarn()
    {
        return $this->belongsTo(Yarn::class, 'yarn_id');
    }

}
