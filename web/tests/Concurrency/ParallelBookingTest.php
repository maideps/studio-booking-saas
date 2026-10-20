<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\Concurrency\ParallelAttempts;
use Tests\Support\StudioFixture;

// Parallel bookings on PostgreSQL, in separate PHP processes that each open
// their own connection (D-005, D-010). Run by tools/test-postgres.mjs.

/**
 * One booking attempt per customer, released at the same moment.
 *
 * @param  list<User>  $customers
 * @return list<string>
 */
function bookInParallel(Tenant $tenant, ClassSession $session, array $customers, int $holdMs = 300): array
{
    return ParallelAttempts::run(
        $tenant,
        array_map(fn (User $customer): array => ['book', $session->id, $customer->id], $customers),
        $holdMs,
    );
}

it('books the last place exactly once when eight customers try at the same moment', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $customers = $studio->tenant->run(function () use ($studio): array {
        $studio->classSession->update(['capacity' => 3]);
        Booking::factory()->count(2)->create(['class_session_id' => $studio->classSession->id]);

        return array_values(User::factory()->customer()->count(8)->create()->all());
    });

    $results = bookInParallel($studio->tenant, $studio->classSession, $customers);

    expect(array_count_values($results))->toBe([
        'booked' => 1,
        'refused: This class is full.' => 7,
    ])->and($studio->tenant->run(fn () => Booking::query()->active()->where('class_session_id', $studio->classSession->id)->count()))
        ->toBe(3);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);

it('books a customer once when the same booking arrives five times at once', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $customer = $studio->customer;

    $results = bookInParallel($studio->tenant, $studio->classSession, array_fill(0, 5, $customer));

    expect(array_count_values($results))->toBe([
        'booked' => 1,
        'refused: You have already booked this class.' => 4,
    ])->and($studio->tenant->run(fn () => Booking::query()->active()->where('customer_id', $customer->id)->count()))
        ->toBe(1);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);

it('keeps released bookings out of the one-active-booking index on PostgreSQL', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $attributes = ['class_session_id' => $studio->classSession->id, 'customer_id' => $studio->customer->id];

    $studio->tenant->run(function () use ($attributes): void {
        Booking::factory()->released()->create($attributes);
        Booking::factory()->released()->create($attributes);
        Booking::factory()->create($attributes);
    });

    expect(fn () => $studio->tenant->run(fn () => Booking::factory()->create($attributes)))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES);
