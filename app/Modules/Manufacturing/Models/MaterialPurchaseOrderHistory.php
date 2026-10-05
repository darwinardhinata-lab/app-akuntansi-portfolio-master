<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialPurchaseOrderHistory extends Model
{
    protected $table = 'mfg_material_purchase_order_histories';

    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'action', 'from_status', 'to_status', 'revision_no', 'actor_id', 'reason'];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // FIX: histori PO hanya dapat ditambahkan; update/save dan delete melalui model ditolak.
        static::updating(function () {
            throw new \RuntimeException('Histori PO bersifat append-only dan tidak boleh diubah.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Histori PO bersifat append-only dan tidak boleh dihapus.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchaseOrder::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
