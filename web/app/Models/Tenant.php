<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Closure;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A studio using the platform. Tenants are central records: this model is
 * not tenant-scoped, and its slug is the tenant's subdomain.
 */
#[Fillable(['name', 'slug'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /** One DNS label: lowercase letters, digits and inner hyphens, at most 63 characters. */
    public const string SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * Run the callback inside this tenant, then restore the previous tenant.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        return app(TenantContext::class)->run($this, $callback);
    }
}
