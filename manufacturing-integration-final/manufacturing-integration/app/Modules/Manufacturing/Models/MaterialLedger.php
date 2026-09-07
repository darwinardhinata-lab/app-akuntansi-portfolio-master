<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — MaterialLedger
 * Tabel: mfg_material_ledgers
 */
class MaterialLedger extends Model
{
    protected $table = 'mfg_material_ledgers';

    protected $fillable = [
        'transaction_date',
        'evidence_number',
        'item_type',
        'item_id',
        'type',
        'qty',
        'unit_cost',
        'total_cost',
        'running_qty',
        'running_value',
        'moving_average_cost',
        'description',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];


}
