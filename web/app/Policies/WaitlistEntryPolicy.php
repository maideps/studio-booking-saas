<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;

/**
 * Customers join and leave waitlists for themselves.
 */
final class WaitlistEntryPolicy
{
    public function create(User $actor, ClassSession $session): bool
    {
        return $actor->hasRole(Role::Customer) && $session->belongsToCurrentTenant();
    }

    public function delete(User $actor, WaitlistEntry $entry): bool
    {
        return $actor->hasRole(Role::Customer)
            && $entry->belongsToCurrentTenant()
            && $entry->customer_id === $actor->id;
    }
}
