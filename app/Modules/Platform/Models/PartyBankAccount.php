<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_id',
        'bank_name',
        'account_no',
        'account_name',
        'currency',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
