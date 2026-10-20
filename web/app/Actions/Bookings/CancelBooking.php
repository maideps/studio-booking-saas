<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * A customer cancels their booking (D-011). Allowed until the studio's
 * cancellation window before the start. The freed place goes to the first
 * customer on the waitlist in the same transaction, under the session lock,
 * so a new booking can never take it first.
 */
final readonly class CancelBooking
{
    public function __construct(
        private TenantContext $context,
        private FillFromWaitlist $fill,
    ) {}

    /**
     * @throws BookingRefused
     */
    public function handle(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $session = ClassSession::lockedForChange($booking->class_session_id);
            $booking = Booking::query()->findOrFail($booking->id);

            if ($booking->active !== true) {
                throw BookingRefused::notActive();
            }

            if (now()->greaterThan($this->context->currentOrFail()->cancellationDeadline($session->starts_at))) {
                throw BookingRefused::tooLateToCancel();
            }

            $booking->active = null;
            $booking->save();

            $this->fill->handle($session);

            return $booking;
        });
    }
}
