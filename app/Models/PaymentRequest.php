<?php

namespace App\Models;

use App\Modules\Platform\Models\Party;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRequest extends Model
{
    protected $fillable = ['company_id', 'party_id', 'request_number', 'request_date', 'category', 'account_code', 'amount', 'status', 'related_document_type', 'related_document_id', 'journal_id', 'notes', 'created_by'];

    public function party(): BelongsTo { return $this->belongsTo(Party::class); }
    public function journal(): BelongsTo { return $this->belongsTo(JournalHeader::class, 'journal_id', 'journal_id'); }
}