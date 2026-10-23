<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a booking was covered (D-012).
 */
enum BookingPayment: string
{
    /** An active membership; nothing is spent. */
    case Membership = 'membership';

    /** One class-pack credit, refunded if the booking is cancelled in time. */
    case Credit = 'credit';

    /** The studio sells nothing, so booking is free. */
    case Free = 'free';
}
