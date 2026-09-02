<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceDetail extends Model
{
    protected $table = 'sales_invoice_details';
    protected $fillable = [
        'sales_invoice_id', 'product_id', 'item_code', 'description', 
        'price', 'qty_actual', 'disc_amount', 'amount', 'is_substitution'
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}