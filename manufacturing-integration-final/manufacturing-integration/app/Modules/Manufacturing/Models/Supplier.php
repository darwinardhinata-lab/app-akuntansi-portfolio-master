<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — Supplier
 * Tabel: mfg_suppliers
 */
class Supplier extends Model
{
    protected $table = 'mfg_suppliers';

    protected $fillable = [
        'supplier_code',
        'supplier_name',
        'supplier_type',
        'helper_code',
        'contact_person',
        'phone',
        'email',
        'address',
        'npwp',
        'payment_terms',
        'credit_days',
        'is_active',
    ];


    public function helperCode()
    {
        return $this->belongsTo(\App\Models\HelperCode::class, 'helper_code', 'helper_code');
    }

    public function knitOrders()
    {
        return $this->hasMany(KnitOrder::class, 'supplier_id');
    }

    public function processingOrders()
    {
        return $this->hasMany(ProcessingOrder::class, 'supplier_id');
    }

    public function stitchingOrders()
    {
        return $this->hasMany(StitchingOrder::class, 'supplier_id');
    }

}
