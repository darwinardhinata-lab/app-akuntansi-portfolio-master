<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    /**
     * Kolom-kolom yang diizinkan untuk diisi secara massal (mass assignment).
     */
    protected $fillable = [
        'tax_code',
        'tax_name',
        'rate',
        'tax_type',
        'account_code',
        'description',
        'is_active',
    ];
}