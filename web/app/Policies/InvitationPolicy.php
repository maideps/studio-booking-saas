<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;

/**
 * Inviting staff and owners is for owners only.
 */
final class InvitationPolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Owner);
    }

    public function delete(User $actor, Invitation $invitation): bool
    {
        return $actor->hasRole(Role::Owner) && $invitation->belongsToCurrentTenant();
    }
}
