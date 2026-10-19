<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The one sanctioned way to read tenant-owned records across tenants (D-002).
 *
 * Meant for platform-level work, such as a scheduled job that fans out per
 * tenant. Request handling never needs it. An architecture test fails if
 * any other code removes the tenant scope or queries through the DB facade.
 */
final class CrossTenant
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    public static function query(string $model): Builder
    {
        if (! in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException(sprintf('%s is not a tenant-owned model.', $model));
        }

        return $model::query()->withoutGlobalScope(TenantScope::class);
    }
}
