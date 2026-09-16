<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTranslation extends Model
{
    protected $fillable = [
        'account_code',
        'locale',
        'name',
        'is_auto_translated',
        'translated_at',
    ];

    protected $casts = [
        'is_auto_translated' => 'boolean',
        'translated_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_code', 'account_code');
    }
}
