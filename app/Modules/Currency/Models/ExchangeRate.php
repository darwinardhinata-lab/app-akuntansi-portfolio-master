<?php

// FIX: multicurrency — nilai kurs selalu string desimal, bukan float.
namespace App\Modules\Currency\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property \Illuminate\Support\Carbon|null $first_used_at */
class ExchangeRate extends Model
{
    protected $table = 'cur_exchange_rates';
    protected $fillable = ['company_id', 'currency_code', 'rate', 'quoted_rate', 'quoted_unit', 'rate_date', 'valid_until', 'type', 'source', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:8', 'quoted_rate' => 'decimal:8', 'quoted_unit' => 'integer', 'rate_date' => 'date', 'valid_until' => 'date', 'first_used_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isUsed(): bool
    {
        return $this->first_used_at !== null;
    }
}