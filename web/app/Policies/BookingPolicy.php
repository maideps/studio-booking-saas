<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\User;

/**
 * Customers book places for themselves. Booking on behalf of a customer is
 * not built yet.
 */
final class BookingPolicy
{
    public function create(User $actor, ClassSession $session): bool
    {
        return $actor->hasRole(Role::Customer) && $session->belongsToCurrentTenant();
    }
}
