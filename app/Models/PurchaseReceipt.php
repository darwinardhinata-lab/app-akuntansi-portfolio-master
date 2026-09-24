<?php

namespace App\Models;

use App\Modules\Platform\Models\Party;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    protected $fillable = ['company_id', 'purchase_order_id', 'party_id', 'receipt_number', 'receipt_date', 'status', 'journal_id', 'notes', 'created_by'];

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function party(): BelongsTo { return $this->belongsTo(Party::class); }
    public function details(): HasMany { return $this->hasMany(PurchaseReceiptDetail::class); }
    public function journal(): BelongsTo { return $this->belongsTo(JournalHeader::class, 'journal_id', 'journal_id'); }
}