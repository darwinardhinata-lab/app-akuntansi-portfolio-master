<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    // FIX: Tambah semua kolom dari migration 2026_06_05_083603_add_tax_columns_to_purchase_orders
    // Sebelumnya: PurchaseOrder::create(['tax_addition_id' => ...]) tidak menyimpan field pajak
    protected $fillable = [
        'po_number', 'transaction_date', 'contact_name', 'location_name',
        'status', 'sub_total', 'grand_total',
        'is_include_ppn',        // ← DITAMBAH
        'tax_addition_id',       // ← DITAMBAH
        'tax_deduction_id',      // ← DITAMBAH
        'tax_addition_amount',   // ← DITAMBAH
        'tax_deduction_amount',  // ← DITAMBAH
    ];

    protected $casts = [
        'is_include_ppn' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'purchase_order_id');
    }
}