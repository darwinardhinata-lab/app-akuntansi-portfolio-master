<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnDetail extends Model
{
    protected $table = 'sales_return_details';

    protected $fillable = [
        'sales_return_id', 'product_id', 'item_code', 'description',
        'qty_returned', 'qty_approved', 'condition',
        'unit_price', 'prorated_discount', 'cogs_value', 'subtotal_refund'
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}