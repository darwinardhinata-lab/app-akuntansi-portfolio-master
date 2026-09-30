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
        'approval_status',
        'fulfillment_status',
        'sub_total',
        'tax_amount',
        'grand_total',
        'remarks',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'po_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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
