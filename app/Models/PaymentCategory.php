<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'cash_bank_flow',
        'tipe_kas_bank',
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeKasBank($query)
    {
        return $query->where('cash_bank_flow', 'KAS_BANK');
    }

    public function scopeManualHutang($query)
    {
        return $query->where('cash_bank_flow', 'MANUAL_HUTANG');
    }
}
