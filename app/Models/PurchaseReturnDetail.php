<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetail extends Model {
    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'item_code',
        'description',
        'qty_returned',
        'qty_approved',
        'condition',
        'unit_price',
        'cogs_value',
        'subtotal_refund',
    ];

    public function header() { return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id'); }
    public function product() { return $this->belongsTo(Product::class); }
}
