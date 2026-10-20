<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

/**
 * The `unique` and `exists` validation rules for tenant-owned tables.
 *
 * Laravel checks these rules with a plain query that skips global scopes,
 * so the tenant scope does not apply: `unique` would reject an email that
 * another tenant uses, and `exists` would accept another tenant's id. These
 * helpers add the current tenant's id explicitly. An architecture test fails
 * on any other use of the rules (D-008).
 */
final class TenantRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $column = 'NULL'): Unique
    {
        return Rule::unique(self::tenantOwned($model), $column)
            ->where('tenant_id', app(TenantContext::class)->currentOrFail()->id);
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function exists(string $model, string $column = 'NULL'): Exists
    {
        return Rule::exists(self::tenantOwned($model), $column)
            ->where('tenant_id', app(TenantContext::class)->currentOrFail()->id);
    }

    /**
     * @param  class-string<Model>  $model
     * @return class-string<Model>
     */
    private static function tenantOwned(string $model): string
    {
        if (! in_array(BelongsToTenant::class, class_uses_recursive($model), true)) {
            throw new InvalidArgumentException(sprintf('%s is not a tenant-owned model.', $model));
        }

        return $model;
    }
}
