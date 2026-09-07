<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — ManufacturingProcess
 * Tabel: mfg_processes
 */
class ManufacturingProcess extends Model
{
    protected $table = 'mfg_processes';

    protected $fillable = [
        'process_type',
        'process_name',
        'rate_unit',
        'process_rate',
        'default_supplier_id',
        'is_active',
    ];


    public function defaultSupplier()
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

}
