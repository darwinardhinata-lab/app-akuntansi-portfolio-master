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
    // FIX (T6): konstanta status ditambahkan agar konsisten dengan lang/*/customs.php
    // dan resources/views/customs/index.blade.php (yang sudah memakai nilai UPPERCASE ini).
    // Sebelumnya status hanya string bebas tanpa satu sumber kebenaran -> rawan typo.
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_UNDER_REVIEW = 'UNDER_REVIEW';
    public const STATUS_NEED_CORRECTION = 'NEED_CORRECTION';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_SPPB_ISSUED = 'SPPB_ISSUED';
    public const STATUS_NPE_ISSUED = 'NPE_ISSUED';
    public const STATUS_VOIDED = 'VOIDED';

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

    // FIX (C2 / Fase 1-B): guard submit sekarang hanya DRAFT dan NEED_CORRECTION
    // (dokumen yang baru dibuat atau butuh perbaikan). QUEUED TIDAK lagi boleh
    // di-submit dari service — status QUEUED ditangani oleh SubmitCustomsDocumentJob
    // (guard di job memeriksa DRAFT/QUEUED secara eksplisit).
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEED_CORRECTION], true);
    }

    // FIX (T5 pendukung): dipakai oleh CustomsDocumentService::updateFromPayload()
    // untuk menolak edit pada dokumen yang statusnya sudah "berjalan" di CEISA.
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEED_CORRECTION], true);
    }

    // FIX (T6): local scope ini dipanggil dari PollCustomsStatusJob via
    // CustomsDocument::submitted() tapi sebelumnya tidak ada di model sama sekali
    // -> "Call to undefined method" setiap kali job polling jalan.
    // Dokumen yang "sedang dalam proses di CEISA" adalah yang berstatus SUBMITTED
    // atau UNDER_REVIEW (belum final: SPPB/NPE/REJECTED/VOIDED).
    public function scopeSubmitted($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW]);
    }


    /**
     * Resolver sumber dokumen.
     * source_type disimpan sebagai slug tunggal sesuai nilai dari controller.
     * Contoh: 'purchase_order', 'purchase_bill', 'sales_order', 'sales_invoice'.
     */
    public function source()
    {
        if (! $this->source_type || ! $this->source_id) {
            return null;
        }

        $mapping = [
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'purchase_bill' => \App\Models\PurchaseBill::class,
            'sales_order' => \App\Models\SalesOrder::class,
            'sales_invoice' => \App\Models\SalesInvoice::class,
        ];

        $modelClass = $mapping[$this->source_type] ?? null;

        if (! $modelClass) {
            return null;
        }

        return (new $modelClass)->find($this->source_id);
    }
}

