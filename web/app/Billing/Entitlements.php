<?php

declare(strict_types=1);

namespace App\Billing;

use App\Enums\BookingPayment;
use App\Enums\CreditReason;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Whether a customer may book in the current studio, and what it costs them
 * (D-012).
 *
 * - A studio that sells nothing is free to book.
 * - Otherwise an active membership covers the booking.
 * - Otherwise one class-pack credit is spent.
 * - Otherwise the booking is refused.
 *
 * Spending a credit checks the balance and then writes to the ledger, so the
 * caller must hold the customer lock (lockCustomer()) while doing both. The
 * lock order is always: the session row, then the customer row.
 */
final readonly class Entitlements
{
    public function __construct(private TenantContext $context) {}

    /**
     * The customer row, locked until the surrounding transaction ends.
     */
    public function lockCustomer(int $customerId): User
    {
        return User::query()->lockForUpdate()->findOrFail($customerId);
    }

    /**
     * How the customer's next booking would be covered.
     *
     * @throws BookingRefused when nothing covers it
     */
    public function paymentFor(User $customer): BookingPayment
    {
        if ($this->context->currentOrFail()->isFreeToBook()) {
            return BookingPayment::Free;
        }

        if ($this->activeMembership($customer) !== null) {
            return BookingPayment::Membership;
        }

        if ($this->creditBalance($customer) > 0) {
            return BookingPayment::Credit;
        }

        throw BookingRefused::notEntitled();
    }

    /**
     * Spends the credit of a booking paid with one. Call with the customer locked.
     */
    public function charge(Booking $booking): void
    {
        if ($booking->paid_with === BookingPayment::Credit) {
            $this->addEntry($booking, -1, CreditReason::Booking);
        }
    }

    /**
     * Gives back the credit of a cancelled booking paid with one.
     */
    public function refund(Booking $booking): void
    {
        if ($booking->paid_with === BookingPayment::Credit) {
            $this->addEntry($booking, 1, CreditReason::Refund);
        }
    }

    public function creditBalance(User $customer): int
    {
        return (int) CreditEntry::query()->where('customer_id', $customer->id)->sum('change');
    }

    public function activeMembership(User $customer): ?Membership
    {
        $membership = Membership::query()->where('customer_id', $customer->id)->first();

        return $membership !== null && $membership->coversBookings() ? $membership : null;
    }

    private function addEntry(Booking $booking, int $change, CreditReason $reason): void
    {
        $entry = new CreditEntry;
        $entry->customer_id = $booking->customer_id;
        $entry->booking_id = $booking->id;
        $entry->change = $change;
        $entry->reason = $reason;
        $entry->save();
    }
}
