<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Billing\Entitlements;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\WaitlistEntry;

/**
 * Gives free places in a session to the customers waiting for it, first in
 * line first (D-011). Called from inside a transaction that already holds the
 * session lock (ClassSession::lockedForChange()), by every action that frees
 * or adds places. It keeps the rule that a session never has a free place
 * while someone is waiting.
 */
final readonly class FillFromWaitlist
{
    public function __construct(private Entitlements $entitlements) {}

    /**
     * @return int how many customers were promoted
     */
    public function handle(ClassSession $locked): int
    {
        $free = $locked->capacity - $locked->activeBookingCount();
        $promoted = 0;

        while ($free > 0) {
            $entry = WaitlistEntry::query()->waiting()->where('class_session_id', $locked->id)->first();

            if ($entry === null) {
                break;
            }

            $entry->active = null;
            $entry->save();

            $alreadyBooked = Booking::query()->active()
                ->where('class_session_id', $locked->id)
                ->where('customer_id', $entry->customer_id)
                ->exists();

            if ($alreadyBooked) {
                continue;
            }

            // The promoted customer pays like any booking. Someone no longer
            // entitled loses their place in line, and the next one is tried.
            $customer = $this->entitlements->lockCustomer($entry->customer_id);

            try {
                $payment = $this->entitlements->paymentFor($customer);
            } catch (BookingRefused) {
                continue;
            }

            $booking = new Booking;
            $booking->class_session_id = $locked->id;
            $booking->customer_id = $entry->customer_id;
            $booking->paid_with = $payment;
            $booking->save();

            $this->entitlements->charge($booking);

            $free--;
            $promoted++;
        }

        return $promoted;
    }
}
