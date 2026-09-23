<?php

namespace App\Modules\Customs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modul Kepabeanan — CustomsStatusHistory
 * Tabel: cst_customs_status_history
 */
class CustomsStatusHistory extends Model
{
    protected $table = 'cst_customs_status_history';

    public $timestamps = false;

    protected $fillable = [
        'customs_document_id',
        'status',
        'note',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function customsDocument(): BelongsTo
    {
        return $this->belongsTo(CustomsDocument::class, 'customs_document_id');
    }
}

