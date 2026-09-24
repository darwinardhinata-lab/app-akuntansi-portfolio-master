<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptDetail extends Model
{
    protected $fillable = ['purchase_receipt_id', 'purchase_order_detail_id', 'product_id', 'item_code', 'description', 'qty_received', 'unit_cost', 'amount'];

    public function receipt(): BelongsTo { return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id'); }
    public function purchaseOrderDetail(): BelongsTo { return $this->belongsTo(PurchaseOrderDetail::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}