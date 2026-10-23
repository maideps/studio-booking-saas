<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A membership's state. feat-008 adds the grace period and suspension that
 * follow a failed payment.
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
}
