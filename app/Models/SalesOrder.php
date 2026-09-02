<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $table = 'sales_orders';

    protected $fillable = [
        'so_number', 'invoice_id', 'invoice_no', 'transaction_date', 'contact_name', 'ref_number', 'salesman',
        'source', 'store_name', 'location_name', 'remarks', 'is_tax_included',
        'receiver_name', 'receiver_address', 'receiver_phone', 'is_cod',
        'tracking_number', 'total_weight', 'is_jubelio_shipment', 'courier',
        'status', 'wms_status', 'is_paid', 'sub_total', 'disc_amount', 'other_discount',
        'tax_amount', 'shipping_cost', 'shipping_discount', 'other_cost',
        'return_remaining', 'grand_total'
    ];

    public function details(): HasMany
    {
        return $this->hasMany(SalesOrderDetail::class, 'sales_order_id', 'id');
    }

    public function salesInvoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SalesInvoice::class, 'invoice_number', 'invoice_no');
    }
}