<?php

declare(strict_types=1);

use App\Actions\Bookings\BookSession;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;

// The booking rules on SQLite. SQLite ignores FOR UPDATE, so the locking
// itself is proven on PostgreSQL in tests/Concurrency/ParallelBookingTest.

function activeBookings(StudioFixture $studio): int
{
    return $studio->tenant->run(fn () => Booking::query()->active()->where('class_session_id', $studio->classSession->id)->count());
}

/**
 * Fills the fixture session with bookings by other customers.
 */
function fillSession(StudioFixture $studio, int $bookings): void
{
    $studio->tenant->run(fn () => Booking::factory()->count($bookings)->create(['class_session_id' => $studio->classSession->id]));
}

it('books the signed-in customer into a session', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'))
        ->assertRedirect($studio->url('/schedule'))
        ->assertSessionHas('status');

    $booking = $studio->tenant->run(fn () => Booking::query()->sole());

    expect($booking->customer_id)->toBe($studio->customer->id)
        ->and($booking->class_session_id)->toBe($studio->classSession->id)
        ->and($booking->active)->toBeTrue();
});

it('shows places left and the customer booking on the schedule', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    fillSession($studio, 3);
    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'));

    actingAs($studio->customer)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.placesLeft', 8)
            ->where('sessions.0.bookedByMe', true)
            ->where('sessions.0.canBook', true));

    actingAs($studio->staff)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.placesLeft', 8)
            ->where('sessions.0.bookedByMe', false)
            ->where('sessions.0.canBook', false));
});

it('refuses a full session', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    fillSession($studio, 12);

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'))
        ->assertRedirect($studio->url('/schedule'))
        ->assertSessionHasErrors(['booking' => 'This class is full.']);

    expect(activeBookings($studio))->toBe(12);
});

it('refuses a second booking by the same customer', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $url = $studio->url('/schedule/{classSession}/bookings');

    actingAs($studio->customer)->post($url)->assertSessionHasNoErrors();
    actingAs($studio->customer)->post($url)->assertSessionHasErrors(['booking' => 'You have already booked this class.']);

    expect(activeBookings($studio))->toBe(1);
});

it('refuses a session that has started', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->run(fn () => $studio->classSession->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]));

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'))
        ->assertSessionHasErrors(['booking' => 'This class has already started.']);

    expect(activeBookings($studio))->toBe(0);
});

it('lets a customer book again once their booking is released', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->run(fn () => Booking::factory()->released()->create([
        'class_session_id' => $studio->classSession->id,
        'customer_id' => $studio->customer->id,
    ]));

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'))->assertSessionHasNoErrors();

    expect(activeBookings($studio))->toBe(1);
});

it('does not count released bookings against capacity', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->run(fn () => Booking::factory()->count(12)->released()->create(['class_session_id' => $studio->classSession->id]));

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/bookings'))->assertSessionHasNoErrors();
});

it('returns 404 for a session of another studio', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);

    actingAs($studio->customer)
        ->post(tenantUrl($tenants->a, "/schedule/{$other->classSession->id}/bookings"))
        ->assertNotFound();

    expect(activeBookings($other))->toBe(0);
});

it('keeps the capacity at or above the places already booked', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    fillSession($studio, 5);
    $payload = [
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
        'starts_at' => now()->addDays(3)->format('Y-m-d').'T18:30',
    ];

    actingAs($studio->owner)->put($studio->url('/schedule/{classSession}'), [...$payload, 'capacity' => 4])
        ->assertSessionHasErrors('capacity');
    actingAs($studio->owner)->put($studio->url('/schedule/{classSession}'), [...$payload, 'capacity' => 5])
        ->assertSessionHasNoErrors();
});

it('lets the database refuse a second active booking but not a released one', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $attributes = ['class_session_id' => $studio->classSession->id, 'customer_id' => $studio->customer->id];

    $studio->tenant->run(function () use ($attributes): void {
        Booking::factory()->released()->create($attributes);
        Booking::factory()->released()->create($attributes);
        Booking::factory()->create($attributes);
    });

    expect(fn () => $studio->tenant->run(fn () => Booking::factory()->create($attributes)))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('lets the database refuse a booking that points at another studio', function (string $column): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);
    $foreignId = $column === 'class_session_id' ? $other->classSession->id : $other->customer->id;

    expect(fn () => $studio->tenant->run(fn () => Booking::factory()->create([
        'class_session_id' => $studio->classSession->id,
        'customer_id' => $studio->customer->id,
        $column => $foreignId,
    ])))->toThrow(QueryException::class);
})->with(['class_session_id', 'customer_id']);

it('turns a racing duplicate into the already-booked answer', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    // The duplicate check passes (no active booking yet), then another request's
    // booking lands just before this insert, as when two requests of one
    // customer race. The insert hits the unique index.
    $raced = false;
    Booking::creating(function () use (&$raced, $studio): void {
        if ($raced) {
            return;
        }

        $raced = true;
        Booking::factory()->create(['class_session_id' => $studio->classSession->id, 'customer_id' => $studio->customer->id]);
    });

    expect(fn () => $studio->tenant->run(fn () => app(BookSession::class)->handle(
        $studio->classSession,
        User::query()->findOrFail($studio->customer->id),
    )))->toThrow(BookingRefused::class, 'You have already booked this class.')
        ->and($raced)->toBeTrue();
});
