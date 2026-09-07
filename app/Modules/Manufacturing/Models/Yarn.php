<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — Yarn
 * Tabel: mfg_yarns
 */
class Yarn extends Model
{
    protected $table = 'mfg_yarns';

    protected $fillable = [
        'yarn_code',
        'yarn_type',
        'yarn_count',
        'composition',
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


    public function issues()
    {
        return $this->hasMany(YarnIssue::class, 'yarn_id');
    }

}
