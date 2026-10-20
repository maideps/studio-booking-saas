<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\User;

/**
 * Every member sees the schedule; owners and staff schedule and edit sessions.
 */
final class ClassSessionPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Owner, Role::Staff);
    }

    public function update(User $actor, ClassSession $session): bool
    {
        return $actor->hasRole(Role::Owner, Role::Staff) && $session->belongsToCurrentTenant();
    }
}
