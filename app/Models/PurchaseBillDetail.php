<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBillDetail extends Model
{
    protected $fillable = [
        'purchase_bill_id',
        'account_code',
        'description',
        'amount',
    ];

    public function header()
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_code', 'account_code');
    }
}