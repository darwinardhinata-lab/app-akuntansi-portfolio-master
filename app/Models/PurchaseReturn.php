<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'return_number',
        'purchase_order_id',
        'return_date',
        'status',
        'notes',
        'total_return_amount',
        'journal_id',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseReturnDetail::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}