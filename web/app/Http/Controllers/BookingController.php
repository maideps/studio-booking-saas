<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Bookings\BookSession;
use App\Exceptions\BookingRefused;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in customer books a place. Authorization is on the route
 * (BookingPolicy); the capacity and duplicate rules are in BookSession.
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
}
