<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Books a place in a session for a customer (D-010).
 *
 * The session row is locked (SELECT … FOR UPDATE) for the whole check and
 * insert, so bookings for the same session run one after another and the
 * count of active bookings is never stale: capacity cannot be exceeded by
 * parallel requests. A second active booking by the same customer is refused
 * here and, if two requests race, by the one-active-booking unique index.
 */
final class BookSession
{
    /**
     * @throws BookingRefused
     */
    public function handle(ClassSession $session, User $customer): Booking
    {
        try {
            return DB::transaction(function () use ($session, $customer): Booking {
                $locked = ClassSession::lockedForChange($session->id);

                if ($locked->starts_at->isPast()) {
                    throw BookingRefused::started();
                }

                $active = Booking::query()->active()->where('class_session_id', $locked->id);

                if ((clone $active)->where('customer_id', $customer->id)->exists()) {
                    throw BookingRefused::alreadyBooked();
                }

                if ($active->count() >= $locked->capacity) {
                    throw BookingRefused::full();
                }

                $booking = new Booking;
                $booking->classSession()->associate($locked);
                $booking->customer()->associate($customer);
                $booking->save();

                return $booking;
            });
        } catch (UniqueConstraintViolationException) {
            throw BookingRefused::alreadyBooked();
        }
    }
}
