<?php

declare(strict_types=1);

namespace App\Enums;

enum CheckoutStatus: string
{
    /** Started at the gateway; not paid, or not seen paid yet. */
    case Pending = 'pending';

    /** Paid and fulfilled. Fulfilling it again does nothing. */
    case Completed = 'completed';
}
