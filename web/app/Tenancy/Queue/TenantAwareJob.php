<?php

declare(strict_types=1);

namespace App\Tenancy\Queue;

/**
 * A queued job that runs inside the tenant it was dispatched for.
 * Implement it with the TenantAware trait.
 */
interface TenantAwareJob
{
    public function tenantId(): int;
}
