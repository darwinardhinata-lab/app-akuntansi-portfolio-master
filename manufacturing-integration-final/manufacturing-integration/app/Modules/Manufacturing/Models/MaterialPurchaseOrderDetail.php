<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — MaterialPurchaseOrderDetail
 * Tabel: mfg_material_purchase_order_details
 */
class MaterialPurchaseOrderDetail extends Model
{
    protected $table = 'mfg_material_purchase_order_details';

    protected $fillable = [
        'po_id',
        'item_type',
        'yarn_id',
        'fabric_id',
        'item_name',
        'qty',
        'unit',
        'rate',
        'amount',
        'qty_received',
    ];


    public function purchaseOrder()
    {
        return $this->belongsTo(MaterialPurchaseOrder::class, 'po_id');
    }

    public function yarn()
    {
        return $this->belongsTo(Yarn::class, 'yarn_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

}
