<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — MaterialReceiptDetail
 * Tabel: mfg_material_receipt_details
 */
class MaterialReceiptDetail extends Model
{
    protected $table = 'mfg_material_receipt_details';

    protected $fillable = [
        'receipt_id',
        'po_detail_id',
        'item_type',
        'yarn_id',
        'fabric_id',
        'item_name',
        'qty',
        'unit',
        'rate',
        'amount',
        'lot_number',
    ];


    public function receipt()
    {
        return $this->belongsTo(MaterialReceipt::class, 'receipt_id');
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
