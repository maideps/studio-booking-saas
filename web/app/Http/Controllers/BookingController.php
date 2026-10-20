<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in customer books or cancels a place. Authorization is on the
 * routes (BookingPolicy); the capacity, duplicate and cancellation-window
 * rules are in the actions.
 */
final class BookingController extends Controller
{
    public function store(Request $request, ClassSession $classSession, BookSession $book): RedirectResponse
    {
        $customer = $request->user();
        assert($customer instanceof User);

        try {
            $book->handle($classSession, $customer);
        } catch (BookingRefused $refused) {
            return redirect()->route('schedule.index')->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('schedule.index')->with('status', __('You are booked in.'));
    }

    public function destroy(Booking $booking, CancelBooking $cancel): RedirectResponse
    {
        try {
            $cancel->handle($booking);
        } catch (BookingRefused $refused) {
            return redirect()->route('schedule.index')->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('schedule.index')->with('status', __('Your booking is cancelled.'));
    }
}
