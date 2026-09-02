<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturn extends Model
{
    protected $table = 'sales_returns';

    protected $fillable = [
        'return_number', 'sales_invoice_id', 'return_date', 
        'status', 'inspected_by', 'approved_at', 
        'refund_shipping_cost', 'return_shipping_cost', 
        'total_refund_amount', 'notes', 'journal_id'
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SalesReturnDetail::class, 'sales_return_id', 'id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id', 'id');
    }
}