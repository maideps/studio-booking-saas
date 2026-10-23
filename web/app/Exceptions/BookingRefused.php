<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking, cancellation or waitlist change the rules do not allow. The
 * message is safe to show the customer.
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

    public static function tooLateToCancel(): self
    {
        return new self(__('It is too late to cancel this class.'));
    }

    public static function notActive(): self
    {
        return new self(__('This booking is already cancelled.'));
    }

    public static function placesAvailable(): self
    {
        return new self(__('This class still has places. Book it instead.'));
    }

    public static function notEntitled(): self
    {
        return new self(__('Buy a membership or a class pack to book.'));
    }

    public static function alreadyWaiting(): self
    {
        return new self(__('You are already on the waitlist for this class.'));
    }
}
