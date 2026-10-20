<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\Role;
use App\Models\User;

/**
 * Gives a member of the current tenant a new role. Callers authorize first
 * (UserPolicy::updateRole); the tenant write guards still refuse a member
 * of another tenant.
 */
final class ChangeMemberRole
{
    public function handle(User $member, Role $role): void
    {
        $member->role = $role;
        $member->save();
    }
}
