<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;

/**
 * Customers book and cancel places for themselves. Booking or cancelling on
 * behalf of a customer is not built yet. The cancellation window is a rule
 * of CancelBooking, not of this policy.
 */
final class BookingPolicy
{
    public function create(User $actor, ClassSession $session): bool
    {
        return $actor->hasRole(Role::Customer) && $session->belongsToCurrentTenant();
    }

    public function cancel(User $actor, Booking $booking): bool
    {
        return $actor->hasRole(Role::Customer)
            && $booking->belongsToCurrentTenant()
            && $booking->customer_id === $actor->id;
    }
}
