<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    protected $table = 'purchase_order_details';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'item_code', 'description', 
        'price', 'qty', 'disc_amount', 'tax_amount', 'amount', 'qty_received'
    ];

    public function header()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}