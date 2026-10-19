<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * The tenant the current request or job runs for (D-002).
 *
 * Registered as a scoped instance, so every request and every queued job
 * starts with no tenant. Code enters a tenant only through run(), which
 * restores the previous tenant afterwards, so a context cannot leak.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function currentOrFail(): Tenant
    {
        return $this->tenant ?? throw TenancyViolation::missingContext();
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        if (! $tenant->exists) {
            throw TenancyViolation::unsavedTenant();
        }

        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
