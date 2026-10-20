<?php

declare(strict_types=1);

namespace App\Actions\Classes;

use App\Actions\Bookings\FillFromWaitlist;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedules a new session, or reschedules an existing one: its template,
 * instructor, start (a UTC instant) and capacity. The end time always
 * follows the template's duration.
 *
 * An existing session is changed under its lock (D-010): the capacity can
 * never drop below the places already booked, and places a higher capacity
 * adds go to the waitlist first (D-011).
 */
final readonly class ScheduleClassSession
{
    public function __construct(private FillFromWaitlist $fill) {}

    /**
     * @param  int<1, 500>  $capacity
     *
     * @throws ValidationException
     */
    public function handle(
        ClassSession $session,
        ClassTemplate $template,
        User $instructor,
        CarbonImmutable $startsAt,
        int $capacity,
    ): ClassSession {
        return DB::transaction(function () use ($session, $template, $instructor, $startsAt, $capacity): ClassSession {
            if ($session->exists && $capacity < ClassSession::lockedForChange($session->id)->activeBookingCount()) {
                throw ValidationException::withMessages([
                    'capacity' => __('The capacity cannot be lower than the places already booked.'),
                ]);
            }

            $session->classTemplate()->associate($template);
            $session->instructor()->associate($instructor);
            $session->starts_at = $startsAt;
            $session->ends_at = $startsAt->addMinutes($template->duration_minutes);
            $session->capacity = $capacity;
            $session->save();

            $this->fill->handle($session);

            return $session;
        });
    }
}
