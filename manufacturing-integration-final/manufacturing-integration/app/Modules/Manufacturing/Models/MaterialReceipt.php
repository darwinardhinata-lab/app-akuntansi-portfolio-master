<?php

namespace App\Modules\Manufacturing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modul Manufaktur — MaterialReceipt
 * Tabel: mfg_material_receipts
 */
class MaterialReceipt extends Model
{
    protected $table = 'mfg_material_receipts';

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'supplier_id',
        'po_id',
        'supplier_doc_no',
        'supplier_doc_date',
        'gross_amount',
        'tax_amount',
        'net_amount',
        'status',
        'journal_id',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'supplier_doc_date' => 'date',
    ];


    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(MaterialPurchaseOrder::class, 'po_id');
    }

    public function details()
    {
        return $this->hasMany(MaterialReceiptDetail::class, 'receipt_id');
    }

    public function journal()
    {
        return $this->belongsTo(\App\Models\JournalHeader::class, 'journal_id', 'journal_id');
    }

}
