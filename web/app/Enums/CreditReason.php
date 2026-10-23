<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a class-pack credit entry exists.
 */
enum CreditReason: string
{
    /** A paid class pack: +N, with its checkout. */
    case Purchase = 'purchase';

    /** A booking paid with a credit: -1, with its booking. */
    case Booking = 'booking';

    /** That booking, cancelled in time: +1, with its booking. */
    case Refund = 'refund';
}
