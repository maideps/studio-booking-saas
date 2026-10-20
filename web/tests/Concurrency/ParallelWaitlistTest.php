<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Tests\Support\Concurrency\ParallelAttempts;
use Tests\Support\StudioFixture;

// Cancellations, promotion and new bookings racing on PostgreSQL, in
// separate PHP processes (D-011). Run by tools/test-postgres.mjs.

/**
 * A session in the fixture studio with the given holders booked in and the
 * given customers waiting, in that order.
 *
 * @return array{session: ClassSession, holders: list<array{booking: Booking, customer: User}>, waiting: list<User>}
 */
function fullSessionWithWaitlist(StudioFixture $studio, int $holders, int $waiting): array
{
    return $studio->tenant->run(function () use ($studio, $holders, $waiting): array {
        $session = $studio->classSession;
        $session->update(['capacity' => $holders]);

        $booked = [];
        // for, not range(): range(1, 0) is [1, 0].
        for ($place = 1; $place <= $holders; $place++) {
            $customer = User::factory()->customer()->create(['name' => "Holder {$place}"]);
            $booked[] = ['booking' => Booking::factory()->create(['class_session_id' => $session->id, 'customer_id' => $customer->id]), 'customer' => $customer];
        }

        $queue = [];
        for ($place = 1; $place <= $waiting; $place++) {
            $customer = User::factory()->customer()->create(['name' => "Waiting {$place}"]);
            WaitlistEntry::factory()->create(['class_session_id' => $session->id, 'customer_id' => $customer->id]);
            $queue[] = $customer;
        }

        return ['session' => $session, 'holders' => $booked, 'waiting' => $queue];
    });
}

/**
 * @return list<User>
 */
function newCustomers(Tenant $tenant, int $count): array
{
    return $tenant->run(fn (): array => array_values(User::factory()->customer()->count($count)->create()->all()));
}

/**
 * @return array{booked: list<string>, waiting: list<string>}
 */
function placesOf(StudioFixture $studio, ClassSession $session): array
{
    return $studio->tenant->run(fn (): array => [
        'booked' => array_values(Booking::query()->active()->where('class_session_id', $session->id)
            ->with('customer')->get()->map(fn (Booking $booking): string => (string) $booking->customer?->name)->sort()->values()->all()),
        'waiting' => array_values(WaitlistEntry::query()->waiting()->where('class_session_id', $session->id)
            ->with('customer')->get()->map(fn (WaitlistEntry $entry): string => (string) $entry->customer?->name)->all()),
    ]);
}

it('gives a cancelled place to the first waiting customer, not to a new booking racing for it', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    ['session' => $session, 'holders' => [$holder]] = fullSessionWithWaitlist($studio, holders: 1, waiting: 2);
    $bookers = newCustomers($studio->tenant, 4);

    $results = ParallelAttempts::run($studio->tenant, [
        ['cancel', $holder['booking']->id, $holder['customer']->id],
        ...array_map(fn (User $customer): array => ['book', $session->id, $customer->id], $bookers),
    ]);

    expect(array_count_values($results))->toBe([
        'cancelled' => 1,
        'refused: This class is full.' => 4,
    ])->and(placesOf($studio, $session))->toBe([
        'booked' => ['Waiting 1'],
        'waiting' => ['Waiting 2'],
    ]);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);

it('never leaves a place free while someone waits, when a cancel races customers joining the waitlist', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    ['session' => $session, 'holders' => [$holder]] = fullSessionWithWaitlist($studio, holders: 1, waiting: 0);
    $joiners = newCustomers($studio->tenant, 4);

    $results = ParallelAttempts::run($studio->tenant, [
        ['cancel', $holder['booking']->id, $holder['customer']->id],
        ...array_map(fn (User $customer): array => ['join', $session->id, $customer->id], $joiners),
    ]);

    // Whatever the order, the cancel succeeds, and either the place is filled
    // from the waitlist or nobody is left waiting for it.
    $places = placesOf($studio, $session);

    expect($results)->toContain('cancelled')
        ->and(count($places['booked']) === 1 || $places['waiting'] === [])->toBeTrue(
            sprintf('A place is free while %d customers wait: %s', count($places['waiting']), json_encode($results)),
        );
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);

it('promotes in order when two places open at the same moment', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    ['session' => $session, 'holders' => $holders] = fullSessionWithWaitlist($studio, holders: 2, waiting: 3);
    $bookers = newCustomers($studio->tenant, 2);

    $results = ParallelAttempts::run($studio->tenant, [
        ...array_map(fn (array $holder): array => ['cancel', $holder['booking']->id, $holder['customer']->id], $holders),
        ...array_map(fn (User $customer): array => ['book', $session->id, $customer->id], $bookers),
    ]);

    expect(array_count_values($results))->toBe([
        'cancelled' => 2,
        'refused: This class is full.' => 2,
    ])->and(placesOf($studio, $session))->toBe([
        'booked' => ['Waiting 1', 'Waiting 2'],
        'waiting' => ['Waiting 3'],
    ]);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);
