<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the current tenant.
 *
 * With no tenant context it throws instead of returning every tenant's rows:
 * a missing context is a bug, and failing closed keeps it from leaking data.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('tenant_id'),
            app(TenantContext::class)->currentOrFail()->id,
        );
    }
}
