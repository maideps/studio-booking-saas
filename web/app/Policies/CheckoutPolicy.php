<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Checkout;
use App\Models\User;

/**
 * Customers buy memberships and class packs for themselves, and only they
 * may complete their own checkout.
 */
final class CheckoutPolicy
{
    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Customer);
    }

    public function complete(User $actor, Checkout $checkout): bool
    {
        return $actor->hasRole(Role::Customer)
            && $checkout->belongsToCurrentTenant()
            && $checkout->customer_id === $actor->id;
    }
}
