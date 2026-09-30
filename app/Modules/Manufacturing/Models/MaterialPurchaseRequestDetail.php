<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseRequestDetail extends Model
{
    protected $table = 'mfg_material_purchase_request_details';

    protected $fillable = ['request_id', 'item_type', 'yarn_id', 'fabric_id', 'item_name', 'qty_requested', 'qty_ordered', 'unit', 'remarks'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseRequest::class, 'request_id');
    }
}