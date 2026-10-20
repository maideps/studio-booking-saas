<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

// The session starts at 18:00 UTC; the studio's window is 12 hours, so the
// last moment to cancel is 06:00 UTC.
beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-11-10 05:00:00', 'UTC')));

function cancellationStudio(int $windowHours = 12): StudioFixture
{
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->update(['cancellation_window_hours' => $windowHours]);
    $studio->tenant->run(fn () => $studio->classSession->update([
        'starts_at' => '2026-11-10 18:00:00',
        'ends_at' => '2026-11-10 19:00:00',
    ]));

    return $studio;
}

function isActive(StudioFixture $studio, Booking $booking): bool
{
    return $studio->tenant->run(fn () => Booking::query()->findOrFail($booking->id)->active === true);
}

it('cancels a booking inside the window and frees the place', function (string $now): void {
    $studio = cancellationStudio();
    $booking = $studio->customerBooking();
    travelTo(CarbonImmutable::parse($now, 'UTC'));

    actingAs($studio->customer)->delete($studio->url('/bookings/{booking}', ['{booking}' => $booking->id]))
        ->assertRedirect($studio->url('/schedule'))
        ->assertSessionHasNoErrors();

    expect(isActive($studio, $booking))->toBeFalse()
        ->and($studio->tenant->run(fn () => $studio->classSession->activeBookingCount()))->toBe(0);
})->with([
    'well before' => '2026-11-09 12:00:00',
    'at the last moment' => '2026-11-10 06:00:00',
]);

it('refuses a cancellation outside the window', function (string $now): void {
    $studio = cancellationStudio();
    $booking = $studio->customerBooking();
    travelTo(CarbonImmutable::parse($now, 'UTC'));

    actingAs($studio->customer)->delete($studio->url('/bookings/{booking}', ['{booking}' => $booking->id]))
        ->assertSessionHasErrors(['booking' => 'It is too late to cancel this class.']);

    expect(isActive($studio, $booking))->toBeTrue();
})->with([
    'a second too late' => '2026-11-10 06:00:01',
    'after the start' => '2026-11-10 18:30:00',
]);

it('follows the studio window', function (): void {
    $studio = cancellationStudio(windowHours: 0);
    $booking = $studio->customerBooking();
    travelTo(CarbonImmutable::parse('2026-11-10 17:59:00', 'UTC'));

    actingAs($studio->customer)->delete($studio->url('/bookings/{booking}', ['{booking}' => $booking->id]))
        ->assertSessionHasNoErrors();

    expect(isActive($studio, $booking))->toBeFalse();
});

it('refuses to cancel a booking twice', function (): void {
    $studio = cancellationStudio();
    $booking = $studio->customerBooking();
    $url = $studio->url('/bookings/{booking}', ['{booking}' => $booking->id]);

    actingAs($studio->customer)->delete($url)->assertSessionHasNoErrors();
    actingAs($studio->customer)->delete($url)->assertSessionHasErrors(['booking' => 'This booking is already cancelled.']);
});

it('does not let a customer cancel another customer booking', function (): void {
    $studio = cancellationStudio();
    $booking = $studio->customerBooking();
    $other = member($studio->tenant, Role::Customer);

    actingAs($other)->delete($studio->url('/bookings/{booking}', ['{booking}' => $booking->id]))->assertForbidden();

    expect(isActive($studio, $booking))->toBeTrue();
});

it('returns 404 for a booking of another studio', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);
    $theirs = $other->customerBooking();

    actingAs($studio->customer)->delete(tenantUrl($tenants->a, "/bookings/{$theirs->id}"))->assertNotFound();

    expect(isActive($other, $theirs))->toBeTrue();
});

it('shows the last moment to cancel on the schedule', function (): void {
    $studio = cancellationStudio();
    $studio->tenant->update(['timezone' => 'Europe/Madrid']);
    $studio->customerBooking();

    actingAs($studio->customer)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.cancelUntil', '2026-11-10T07:00')
            ->where('sessions.0.canCancel', true)
            ->where('sessions.0.myBookingId', fn (?int $id): bool => $id !== null));

    travelTo(CarbonImmutable::parse('2026-11-10 06:30:00', 'UTC'));

    actingAs($studio->customer)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page->where('sessions.0.canCancel', false));
});
