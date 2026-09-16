<?php

namespace App\Modules\Customs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Kepabeanan — CustomsDocumentLog
 * Tabel: cst_customs_document_logs
 * 
 * Tabel ini bersifat IMMUTABLE (append-only).
 * Override update()/delete() untuk mencegah perubahan.
 */
class CustomsDocumentLog extends Model
{
    protected $table = 'cst_customs_document_logs';

    public $timestamps = false;

    protected $fillable = [
        'customs_document_id',
        'direction',
        'event_type',
        'http_status',
        'request_payload',
        'response_payload',
        'correlation_id',
        'latency_ms',
        'actor_user_id',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function customsDocument(): BelongsTo
    {
        return $this->belongsTo(CustomsDocument::class, 'customs_document_id');
    }

    /**
     * Immutability guard: menolak update()
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \RuntimeException('CustomsDocumentLog bersifat immutable. Tidak boleh diupdate.');
    }

    /**
     * Immutability guard: menolak delete()
     */
    public function delete()
    {
        throw new \RuntimeException('CustomsDocumentLog bersifat immutable. Tidak boleh dihapus.');
    }
}
