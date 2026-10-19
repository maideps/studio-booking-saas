<?php

declare(strict_types=1);

namespace App\Tenancy;

use LogicException;

/**
 * Thrown when code touches tenant-owned data without the right tenant context.
 * It always signals a bug, never a user error, so it is not caught.
 */
final class TenancyViolation extends LogicException
{
    public static function missingContext(): self
    {
        return new self('Tenant-owned data was accessed outside a tenant context. Run the code inside $tenant->run(), or use CrossTenant::query() for deliberate cross-tenant reads.');
    }

    public static function unsavedTenant(): self
    {
        return new self('Code cannot run inside a tenant that has not been saved.');
    }

    public static function foreignWrite(string $model, mixed $recordTenantId, int $currentTenantId): self
    {
        return new self(sprintf(
            'A %s of tenant %s cannot be written from tenant %d.',
            $model,
            is_scalar($recordTenantId) ? (string) $recordTenantId : 'unknown',
            $currentTenantId,
        ));
    }

    public static function tenantChanged(string $model): self
    {
        return new self(sprintf('The tenant of a %s cannot be changed.', $model));
    }

    public static function unknownTenant(int $tenantId): self
    {
        return new self(sprintf('Tenant %d does not exist.', $tenantId));
    }

    public static function jobWithoutTenant(string $job): self
    {
        return new self(sprintf('%s did not capture a tenant. Call captureTenant() in its constructor.', $job));
    }
}
