<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Puts a customer at the end of a full session's waitlist (D-011). Only a
 * full session has a waitlist: with free places the customer books instead.
 */
final class JoinWaitlist
{
    /**
     * @throws BookingRefused
     */
    public function handle(ClassSession $session, User $customer): WaitlistEntry
    {
        try {
            return DB::transaction(function () use ($session, $customer): WaitlistEntry {
                $locked = ClassSession::lockedForChange($session->id);

                if ($locked->starts_at->isPast()) {
                    throw BookingRefused::started();
                }

                $booked = Booking::query()->active()
                    ->where('class_session_id', $locked->id)
                    ->where('customer_id', $customer->id)
                    ->exists();

                if ($booked) {
                    throw BookingRefused::alreadyBooked();
                }

                $waiting = WaitlistEntry::query()->waiting()
                    ->where('class_session_id', $locked->id)
                    ->where('customer_id', $customer->id)
                    ->exists();

                if ($waiting) {
                    throw BookingRefused::alreadyWaiting();
                }

                if ($locked->activeBookingCount() < $locked->capacity) {
                    throw BookingRefused::placesAvailable();
                }

                $entry = new WaitlistEntry;
                $entry->classSession()->associate($locked);
                $entry->customer()->associate($customer);
                $entry->save();

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            throw BookingRefused::alreadyWaiting();
        }
    }
}
