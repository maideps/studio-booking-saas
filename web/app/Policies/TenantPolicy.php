<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Studio settings. Tenants are central records, so this policy also checks
 * that the studio is the one the request runs in.
 */
final class TenantPolicy
{
    public function update(User $actor, Tenant $tenant): bool
    {
        return $actor->hasRole(Role::Owner)
            && $tenant->is(app(TenantContext::class)->current());
    }
}
