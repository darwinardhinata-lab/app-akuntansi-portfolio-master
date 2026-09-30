<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionLine extends Model
{
    protected $table = 'mfg_production_lines';

    protected $fillable = [
        'line_code', 'line_name', 'area', 'daily_capacity', 'is_active', 'remarks',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'daily_capacity' => 'integer'];
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'line_id');
    }
}