<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — MaterialPurchaseOrder
 * Tabel: mfg_material_purchase_orders
 */
class MaterialPurchaseOrder extends Model
{
    protected $table = 'mfg_material_purchase_orders';

    protected $fillable = [
        'po_number',
        'po_date',
        'supplier_id',
        'status',
        'sub_total',
        'tax_amount',
        'grand_total',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];


    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function details()
    {
        return $this->hasMany(MaterialPurchaseOrderDetail::class, 'po_id');
    }

}
