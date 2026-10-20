<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking the rules do not allow. The message is safe to show the customer.
 */
final class BookingRefused extends RuntimeException
{
    public static function full(): self
    {
        return new self(__('This class is full.'));
    }

    public static function alreadyBooked(): self
    {
        return new self(__('You have already booked this class.'));
    }

    public static function started(): self
    {
        return new self(__('This class has already started.'));
    }
}
