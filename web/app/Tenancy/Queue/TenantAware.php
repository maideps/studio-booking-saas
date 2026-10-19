<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantContext;

/**
 * Carries the tenant into a queued job explicitly (D-002).
 *
 * The job's constructor calls captureTenant(), which stores the current
 * tenant id as a serialized property. On the worker, RunInTenantContext
 * runs the job inside that tenant. A job that defines its own middleware()
 * must include RunInTenantContext. Pass ids rather than models with loaded
 * tenant-owned relations: Laravel reloads those relations before the job
 * middleware has set the tenant.
 *
 * @phpstan-require-implements TenantAwareJob
 */
trait TenantAware
{
    /** The tenant the job was dispatched for; set only by captureTenant(). */
    protected int $tenantId;

    public function tenantId(): int
    {
        if (! isset($this->tenantId)) {
            throw TenancyViolation::jobWithoutTenant(static::class);
        }

        return $this->tenantId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RunInTenantContext];
    }

    protected function captureTenant(): void
    {
        $this->tenantId = app(TenantContext::class)->currentOrFail()->id;
    }
}
