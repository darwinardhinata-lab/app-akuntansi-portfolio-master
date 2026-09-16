<?php

namespace App\Modules\Customs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modul Kepabeanan — CustomsDocument
 * Tabel: cst_customs_documents
 */
class CustomsDocument extends Model
{
    protected $table = 'cst_customs_documents';

    protected $fillable = [
        'document_type',
        'internal_number',
        'source_type',
        'source_id',
        'nomor_aju',
        'nomor_pendaftaran',
        'status',
        'environment',
        'kode_kantor',
        'currency',
        'exchange_rate',
        'total_value',
        'submitted_at',
        'responded_at',
        'last_error_code',
        'last_error_message',
        'retry_count',
        'payload_hash',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:4',
        'total_value' => 'decimal:2',
        'submitted_at' => 'datetime',
        'responded_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(CustomsDocumentDetail::class, 'customs_document_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CustomsDocumentLog::class, 'customs_document_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CustomsStatusHistory::class, 'customs_document_id');
    }

    /**
     * Resolver sumber dokumen.
     * source_type diisi dengan nama tabel (konvensi proyek ini), bukan nama class.
     * Contoh: 'purchase_orders', 'purchase_bills', 'sales_orders', 'sales_invoices'.
     * Keputusan ini diambil karena codebase ini konsisten menggunakan nama tabel
     * sebagai identifier di kolom *_type (ditemui di log audit dan sistem tracking lain).
     */
    public function source()
    {
        if (! $this->source_type || ! $this->source_id) {
            return null;
        }

        $mapping = [
            'purchase_orders' => \App\Models\PurchaseOrder::class,
            'purchase_bills' => \App\Models\PurchaseBill::class,
            'sales_orders' => \App\Models\SalesOrder::class,
            'sales_invoices' => \App\Models\SalesInvoice::class,
        ];

        $modelClass = $mapping[$this->source_type] ?? null;

        if (! $modelClass) {
            return null;
        }

        return (new $modelClass)->find($this->source_id);
    }
}

