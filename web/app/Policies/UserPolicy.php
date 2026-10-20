<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Who may manage a studio's members. Gate::before (AppServiceProvider) has
 * already denied any user outside the current tenant, so these rules only
 * decide by role.
 */
final class UserPolicy
{
    /**
     * List the studio's members: owners and staff.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Owner, Role::Staff);
    }

    /**
     * Change a member's role: owners only, and never their own role, so a
     * studio cannot lose its last owner by accident.
     */
    public function updateRole(User $actor, User $member): bool
    {
        return $actor->hasRole(Role::Owner)
            && $member->belongsToCurrentTenant()
            && ! $actor->is($member);
    }
}
