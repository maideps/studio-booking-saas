<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Classes\ScheduleClassSession;
use App\Enums\Role;
use App\Http\Requests\Classes\ClassSessionRequest;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's schedule. Times go to the page as wall-clock time in the
 * studio timezone. Authorization is on the routes (ClassSessionPolicy).
 */
final class ClassSessionController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        assert($actor instanceof User);
        $tenant = $this->context->currentOrFail();

        $myBookings = Booking::query()->active()->where('customer_id', $actor->id)->get()->keyBy('class_session_id');
        $myEntries = WaitlistEntry::query()->waiting()->where('customer_id', $actor->id)->get()->keyBy('class_session_id');

        $sessions = ClassSession::query()
            ->upcoming()
            ->with(['classTemplate', 'instructor'])
            ->withCount([
                'bookings as active_bookings_count' => fn (Builder $query) => $query->where('active', true),
                'waitlistEntries as waiting_count' => fn (Builder $query) => $query->where('active', true),
            ])
            ->get()
            ->map(function (ClassSession $session) use ($actor, $tenant, $myBookings, $myEntries): array {
                $placesLeft = max(0, $session->capacity - (int) $session->getAttribute('active_bookings_count'));
                $booking = $myBookings->get($session->id);
                $entry = $myEntries->get($session->id);
                $cancelDeadline = $tenant->cancellationDeadline($session->starts_at);

                return [
                    'id' => $session->id,
                    'className' => $session->classTemplate?->name,
                    'instructorName' => $session->instructor?->name,
                    'startsAt' => $tenant->localTime($session->starts_at)->format(Tenant::LOCAL_TIME_FORMAT),
                    'endsAt' => $tenant->localTime($session->ends_at)->format(Tenant::LOCAL_TIME_FORMAT),
                    'capacity' => $session->capacity,
                    'placesLeft' => $placesLeft,
                    'waitingCount' => (int) $session->getAttribute('waiting_count'),
                    'bookedByMe' => $booking !== null,
                    'myBookingId' => $booking?->id,
                    'cancelUntil' => $tenant->localTime($cancelDeadline)->format(Tenant::LOCAL_TIME_FORMAT),
                    'canCancel' => $booking !== null && $actor->can('cancel', $booking) && ! now()->greaterThan($cancelDeadline),
                    'myWaitlistEntryId' => $entry?->id,
                    'waitlistPosition' => $entry === null ? null : WaitlistEntry::query()->waiting()
                        ->where('class_session_id', $session->id)
                        ->where('id', '<=', $entry->id)
                        ->count(),
                    'canBook' => $actor->can('create', [Booking::class, $session]),
                    'canJoinWaitlist' => $booking === null && $entry === null && $placesLeft === 0
                        && $actor->can('create', [WaitlistEntry::class, $session]),
                    'canUpdate' => $actor->can('update', $session),
                ];
            });

        return Inertia::render('Schedule/Index', [
            'sessions' => $sessions,
            'timezone' => $tenant->timezone,
            'canCreate' => $actor->can('create', ClassSession::class),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(ClassSessionRequest $request, ScheduleClassSession $schedule): RedirectResponse
    {
        $schedule->handle(new ClassSession, $request->template(), $request->instructor(), $request->startsAt(), $request->capacity());

        return redirect()->route('schedule.index')->with('status', __('Session scheduled.'));
    }

    public function edit(ClassSession $classSession): Response
    {
        return $this->form($classSession);
    }

    public function update(ClassSessionRequest $request, ClassSession $classSession, ScheduleClassSession $schedule): RedirectResponse
    {
        $schedule->handle($classSession, $request->template(), $request->instructor(), $request->startsAt(), $request->capacity());

        return redirect()->route('schedule.index')->with('status', __('Session updated.'));
    }

    private function form(?ClassSession $session): Response
    {
        $tenant = $this->context->currentOrFail();

        return Inertia::render('Schedule/Form', [
            'session' => $session === null ? null : [
                'id' => $session->id,
                'class_template_id' => $session->class_template_id,
                'instructor_id' => $session->instructor_id,
                'starts_at' => $tenant->localTime($session->starts_at)->format(Tenant::LOCAL_TIME_FORMAT),
                'capacity' => $session->capacity,
            ],
            'templates' => ClassTemplate::query()->orderBy('name')->get()->map(fn (ClassTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'capacity' => $template->capacity,
            ]),
            'instructors' => User::query()
                ->whereIn('role', [Role::Owner, Role::Staff])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name]),
            'timezone' => $tenant->timezone,
        ]);
    }
}
