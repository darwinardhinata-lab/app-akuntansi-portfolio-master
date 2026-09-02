<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $fillable = [
        'journal_detail_id',
        'asset_code',
        'asset_name',
        'category',
        'quantity',
        'purchase_date',
        'purchase_price',
        'residual_value',
        'useful_life_months',
        'is_active'
    ];

    // B3 FIX: Tambah $casts untuk kolom uang agar tidak terjadi galat presisi floating-point
    // saat kalkulasi depresiasi atau valuasi aset tetap.
    protected $casts = [
        'purchase_price'     => 'decimal:2',
        'residual_value'     => 'decimal:2',
        'purchase_date'      => 'date',
        'useful_life_months' => 'integer',
        'quantity'           => 'integer',
        'is_active'          => 'boolean',
    ];

    // --- TAMBAHKAN JEMBATAN INI ---
    public function journalDetail()
    {
        return $this->belongsTo(JournalDetail::class, 'journal_detail_id', 'id');
    }
}