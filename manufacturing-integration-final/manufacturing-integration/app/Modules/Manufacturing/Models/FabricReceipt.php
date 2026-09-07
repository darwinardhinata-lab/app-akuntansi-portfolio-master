<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — FabricReceipt
 * Tabel: mfg_fabric_receipts
 */
class FabricReceipt extends Model
{
    protected $table = 'mfg_fabric_receipts';

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'processing_order_id',
        'fabric_id',
        'qty_received',
        'qty_rejected',
        'lot_number',
        'color',
        'shade_code',
        'shrinkage_percent',
        'process_cost_amount',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];


    public function processingOrder()
    {
        return $this->belongsTo(ProcessingOrder::class, 'processing_order_id');
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class, 'fabric_id');
    }

}
