<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Bookings\JoinWaitlist;
use App\Actions\Bookings\LeaveWaitlist;
use App\Exceptions\BookingRefused;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in customer joins or leaves a session's waitlist. Authorization
 * is on the routes (WaitlistEntryPolicy).
 */
final class WaitlistController extends Controller
{
    public function store(Request $request, ClassSession $classSession, JoinWaitlist $join): RedirectResponse
    {
        $customer = $request->user();
        assert($customer instanceof User);

        try {
            $join->handle($classSession, $customer);
        } catch (BookingRefused $refused) {
            return redirect()->route('schedule.index')->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('schedule.index')->with('status', __('You are on the waitlist.'));
    }

    public function destroy(WaitlistEntry $waitlistEntry, LeaveWaitlist $leave): RedirectResponse
    {
        $leave->handle($waitlistEntry);

        return redirect()->route('schedule.index')->with('status', __('You left the waitlist.'));
    }
}
