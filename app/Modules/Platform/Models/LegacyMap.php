<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_system',
        'source_entity',
        'source_key',
        'source_company',
        'target_entity',
        'target_key',
        'transform_version',
        'approved_by',
        'checksum',
        'status',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Bagian 6: "Konflik tidak memakai last-write-wins." Baris ambigu harus
     * eksplisit dikarantina, bukan otomatis ditimpa. Gunakan method ini
     * dari service pemetaan alih-alih upsert langsung.
     */
    public function quarantine(): void
    {
        $this->status = 'QUARANTINED';
        $this->save();
    }
}
