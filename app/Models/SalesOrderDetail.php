<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderDetail extends Model
{
    protected $table = 'sales_order_details';

    protected $fillable = [
        'sales_order_id', 'product_id', 'item_code', 
        'description', 'price', 'qty', 'qty_shipped', 
        'disc_amount', 'tax_amount', 'amount'
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}