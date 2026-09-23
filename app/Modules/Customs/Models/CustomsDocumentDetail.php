<?php

namespace App\Modules\Customs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Kepabeanan — CustomsDocumentDetail
 * Tabel: cst_customs_document_details
 */
class CustomsDocumentDetail extends Model
{
    protected $table = 'cst_customs_document_details';

    protected $fillable = [
        'customs_document_id',
        'product_id',
        'hs_code',
        'deskripsi_barang',
        'qty',
        'satuan',
        'berat_bersih',
        'nilai',
        'tarif_bm',
        'tarif_ppn',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'berat_bersih' => 'decimal:4',
        'nilai' => 'decimal:2',
        'tarif_bm' => 'decimal:4',
        'tarif_ppn' => 'decimal:4',
    ];

    public function customsDocument(): BelongsTo
    {
        return $this->belongsTo(CustomsDocument::class, 'customs_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }
}

