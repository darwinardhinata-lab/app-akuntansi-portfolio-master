<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';
    protected $fillable = [
        'invoice_number', 'sales_order_id', 'transaction_date', 
        'contact_name', 'sub_total', 'disc_amount', 'tax_amount', 
        'shipping_cost', 'grand_total', 'payment_status', 'journal_id'
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SalesInvoiceDetail::class, 'sales_invoice_id', 'id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'id');
    }
}