<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

use App\Models\Tenant;
use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantContext;
use Closure;

/**
 * Job middleware: runs a TenantAwareJob inside the tenant it carries and
 * restores the previous context afterwards. A deleted tenant fails the job.
 */
final class RunInTenantContext
{
    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $tenant = Tenant::query()->find($job->tenantId())
            ?? throw TenancyViolation::unknownTenant($job->tenantId());

        return app(TenantContext::class)->run($tenant, fn (): mixed => $next($job));
    }
}
