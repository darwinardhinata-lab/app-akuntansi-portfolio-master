<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLedger extends Model
{
    protected $fillable = [
        'transaction_date', 'evidence_number', 'product_id', 'type', 'qty', 
        'unit_cost', 'total_cost', 'running_qty', 'running_value', 'moving_average_cost', 'description'
    ];

    // B4 FIX: Tambah $casts untuk kolom uang dan kuantitas.
    // Tanpa cast, moving average cost bisa akumulasi galat floating-point
    // setiap transaksi — sangat berbahaya di sistem inventory.
    protected $casts = [
        'transaction_date'    => 'date',
        'qty'                 => 'decimal:4',
        'unit_cost'           => 'decimal:2',
        'total_cost'          => 'decimal:2',
        'running_qty'         => 'decimal:4',
        'running_value'       => 'decimal:2',
        'moving_average_cost' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}