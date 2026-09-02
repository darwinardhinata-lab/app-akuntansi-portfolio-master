<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku', 'name', 'variation', 'category_name', 'unit', 
        'sell_price', 'average_cost', 'stock_quantity', 
        'inventory_account_code', 'cogs_account_code'
    ];

    public function ledgers()
    {
        return $this->hasMany(InventoryLedger::class);
    }
}