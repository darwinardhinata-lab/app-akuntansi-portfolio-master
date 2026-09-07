<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — GreyFabricReceipt
 * Tabel: mfg_grey_fabric_receipts
 */
class GreyFabricReceipt extends Model
{
    protected $table = 'mfg_grey_fabric_receipts';

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'knit_order_id',
        'fabric_id',
        'qty_received',
        'qty_rejected',
        'lot_number',
        'gsm_actual',
        'knitting_cost_amount',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];


    public function knitOrder()
    {
        return $this->belongsTo(KnitOrder::class, 'knit_order_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

}
