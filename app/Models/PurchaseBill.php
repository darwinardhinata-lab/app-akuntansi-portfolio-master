<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBill extends Model
{
    protected $fillable = [
        'bill_number',
        'purchase_order_id',
        'transaction_date',
        'contact_name',
        'sub_total',
        'disc_amount',
        'tax_amount',
        'shipping_cost',
        'grand_total',
        'journal_id',
        'payment_status',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseBillDetail::class);
    }
}