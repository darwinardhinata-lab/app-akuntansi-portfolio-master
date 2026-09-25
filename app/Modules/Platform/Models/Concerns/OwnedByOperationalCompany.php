<?php

namespace App\Modules\Platform\Models\Concerns;

use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Support\OperationalCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

trait OwnedByOperationalCompany
{
    protected static function bootOwnedByOperationalCompany(): void
    {
        static::addGlobalScope('operational_company', function (Builder $query) {
            $context = app(OperationalCompany::class);
            if ($context->enabled()) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $context->id());
            }
        });
        static::saving(function ($model) {
            if (! app(OperationalCompany::class)->enabled()) {
                return;
            }
            $companyId = app(OperationalCompany::class)->id();
            if ($model->exists && (int) $model->getRawOriginal('company_id') !== $companyId) {
                throw ValidationException::withMessages(['company_id' => 'Pemilik dokumen tidak valid.']);
            }
            if ($model->getAttribute('company_id') !== null && (int) $model->getAttribute('company_id') !== $companyId) {
                throw ValidationException::withMessages(['company_id' => 'Pemilik perusahaan tidak dapat diganti.']);
            }
            if ($model->exists && $model->isDirty('company_id')) {
                throw ValidationException::withMessages(['company_id' => 'Pemilik perusahaan tidak dapat diganti.']);
            }
            $model->setAttribute('company_id', $companyId);
            if ($model->party_id) {
                $allowedRoles = $model->getTable() === 'purchase_orders' ? ['SUPPLIER', 'SUBCONTRACTOR'] : ['CUSTOMER'];
                // Validate new/changed links. An unchanged inactive master may stay on a historical document.
                $query = \App\Modules\Platform\Models\Party::whereKey($model->party_id)->where('company_id', $companyId);
                if (! $model->exists || $model->isDirty('party_id')) {
                    $query->where('active', true)->whereHas('roles', fn ($q) => $q->where('active', true)->whereIn('role', $allowedRoles));
                }
                if (! $query->exists()) {
                    throw ValidationException::withMessages(['party_id' => 'Party tidak sesuai pemilik/peran dokumen.']);
                }
            }
        });
        static::deleting(function ($model) {
            if (app(OperationalCompany::class)->enabled()) {
                abort_unless((int) $model->company_id === app(OperationalCompany::class)->id(), 404);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
