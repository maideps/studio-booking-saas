<?php

declare(strict_types=1);

namespace App\Actions\Bookings;

use App\Models\ClassSession;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

/**
 * A customer leaves a waitlist. Leaving frees no place, so nobody is
 * promoted; the session lock keeps the order of changes the same as for
 * every other place change (D-011).
 */
final class LeaveWaitlist
{
    public function handle(WaitlistEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            ClassSession::lockedForChange($entry->class_session_id);
            $entry = WaitlistEntry::query()->findOrFail($entry->id);

            if ($entry->active === true) {
                $entry->active = null;
                $entry->save();
            }
        });
    }
}
