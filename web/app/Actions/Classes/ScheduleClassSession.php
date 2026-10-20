<?php

declare(strict_types=1);

namespace App\Actions\Classes;

use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Schedules a new session, or reschedules an existing one: its template,
 * instructor, start (a UTC instant) and capacity. The end time always
 * follows the template's duration.
 */
final class ScheduleClassSession
{
    /**
     * @param  int<1, 500>  $capacity
     */
    public function handle(
        ClassSession $session,
        ClassTemplate $template,
        User $instructor,
        CarbonImmutable $startsAt,
        int $capacity,
    ): ClassSession {
        $session->classTemplate()->associate($template);
        $session->instructor()->associate($instructor);
        $session->starts_at = $startsAt;
        $session->ends_at = $startsAt->addMinutes($template->duration_minutes);
        $session->capacity = $capacity;
        $session->save();

        return $session;
    }
}
