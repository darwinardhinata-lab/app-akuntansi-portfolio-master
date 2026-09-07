<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — Fabric
 * Tabel: mfg_fabrics
 */
class Fabric extends Model
{
    protected $table = 'mfg_fabrics';

    protected $fillable = [
        'fabric_code',
        'fabric_type',
        'subtype',
        'state',
        'gsm',
        'composition',
        'width',
        'color',
        'unit',
        'stock_quantity',
        'average_cost',
        'inventory_account_code',
        'is_active',
    ];

    protected $casts = [
        'stock_quantity' => 'decimal:2',
        'average_cost' => 'decimal:2',
    ];


    public function scopeGrey($query)
    {
        return $query->where('state', 'GREY');
    }

    public function scopeFinished($query)
    {
        return $query->where('state', 'FINISHED');
    }

}
