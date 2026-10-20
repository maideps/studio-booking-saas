<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Marks a model as tenant-owned (D-002). Every model that holds tenant data
 * uses this trait; an architecture test enforces it.
 *
 * - Queries only see the current tenant's rows (TenantScope).
 * - New records get the current tenant's id.
 * - Writes outside a tenant context, for another tenant, or that change
 *   tenant_id are refused, including bulk updates (TenantBuilder).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $tenantId = app(TenantContext::class)->currentOrFail()->id;

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $tenantId);
            }

            self::guardWrite($model, $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenancyViolation::tenantChanged($model::class);
            }

            self::guardWrite($model, app(TenantContext::class)->currentOrFail()->id);
        });

        static::deleting(function (Model $model): void {
            self::guardWrite($model, app(TenantContext::class)->currentOrFail()->id);
        });
    }

    /**
     * Bulk updates go through TenantBuilder, which refuses to write tenant_id.
     *
     * @param  QueryBuilder  $query
     * @return TenantBuilder<*>
     */
    public function newEloquentBuilder($query): TenantBuilder
    {
        return new TenantBuilder($query);
    }

    /**
     * Whether this record belongs to the tenant the code runs in. False when
     * there is no tenant context.
     */
    public function belongsToCurrentTenant(): bool
    {
        $current = app(TenantContext::class)->current();
        $recordTenantId = $this->getAttribute('tenant_id');

        return $current !== null && is_numeric($recordTenantId) && (int) $recordTenantId === $current->id;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    private static function guardWrite(Model $model, int $currentTenantId): void
    {
        $recordTenantId = $model->getAttribute('tenant_id');

        if (! is_numeric($recordTenantId) || (int) $recordTenantId !== $currentTenantId) {
            throw TenancyViolation::foreignWrite($model::class, $recordTenantId, $currentTenantId);
        }
    }
}
