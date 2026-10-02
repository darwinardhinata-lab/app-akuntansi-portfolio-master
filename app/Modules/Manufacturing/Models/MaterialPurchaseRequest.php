<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialPurchaseRequest extends Model
{
    public const DRAFT = 'DRAFT';

    public const SUBMITTED = 'SUBMITTED';

    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    protected $table = 'mfg_material_purchase_requests';

    protected $fillable = ['request_number', 'request_date', 'required_date', 'source_work_order_id', 'approval_status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['request_date' => 'date', 'required_date' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function details(): HasMany
    {
        return $this->hasMany(MaterialPurchaseRequestDetail::class, 'request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
