<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseRequestHistory extends Model
{
    protected $table = 'mfg_material_purchase_request_histories';

    public const UPDATED_AT = null;

    protected $fillable = ['request_id', 'action', 'from_status', 'to_status', 'revision_no', 'actor_id', 'reason'];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // FIX: histori hanya dapat ditambahkan; perubahan dan penghapusan melalui model ditolak.
        static::updating(function () {
            throw new \RuntimeException('Histori PR bersifat append-only dan tidak boleh diubah.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Histori PR bersifat append-only dan tidak boleh dihapus.');
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseRequest::class, 'request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
