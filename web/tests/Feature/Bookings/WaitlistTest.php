<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Booking;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;

/**
 * A full fixture session (capacity 1, booked by the member) with customers
 * waiting in the given order.
 *
 * @return list<User>
 */
function waitingCustomers(StudioFixture $studio, int $count): array
{
    $studio->fillSession();
    $customers = [];

    foreach (range(1, $count) as $place) {
        $customer = member($studio->tenant, Role::Customer, ['name' => "Waiting {$place}"]);
        actingAs($customer)->post($studio->url('/schedule/{classSession}/waitlist'))->assertSessionHasNoErrors();
        $customers[] = $customer;
    }

    return $customers;
}

/**
 * The names of the customers holding a place in the fixture session.
 *
 * @return list<string>
 */
function bookedNames(StudioFixture $studio): array
{
    return array_values($studio->tenant->run(fn () => Booking::query()->active()
        ->where('class_session_id', $studio->classSession->id)
        ->with('customer')->orderBy('id')->get()
        ->map(fn (Booking $booking): string => (string) $booking->customer?->name)
        ->all()));
}

/**
 * The names of the customers still waiting, first in line first.
 *
 * @return list<string>
 */
function waitingNames(StudioFixture $studio): array
{
    return array_values($studio->tenant->run(fn () => WaitlistEntry::query()->waiting()
        ->where('class_session_id', $studio->classSession->id)
        ->with('customer')->get()
        ->map(fn (WaitlistEntry $entry): string => (string) $entry->customer?->name)
        ->all()));
}

function cancelMemberBooking(StudioFixture $studio): void
{
    $booking = $studio->tenant->run(fn () => Booking::query()->active()
        ->where('class_session_id', $studio->classSession->id)
        ->orderBy('id')
        ->firstOrFail());
    $holder = $studio->tenant->run(fn () => User::query()->findOrFail($booking->customer_id));

    actingAs($holder)->delete($studio->url('/bookings/{booking}', ['{booking}' => $booking->id]))->assertSessionHasNoErrors();
}

it('promotes waiting customers in the order they joined', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    waitingCustomers($studio, 3);

    expect(waitingNames($studio))->toBe(['Waiting 1', 'Waiting 2', 'Waiting 3']);

    cancelMemberBooking($studio);
    expect(bookedNames($studio))->toBe(['Waiting 1'])
        ->and(waitingNames($studio))->toBe(['Waiting 2', 'Waiting 3']);

    cancelMemberBooking($studio);
    expect(bookedNames($studio))->toBe(['Waiting 2'])
        ->and(waitingNames($studio))->toBe(['Waiting 3']);
});

it('skips a customer who left the waitlist', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    [$first] = waitingCustomers($studio, 3);
    $entry = $studio->tenant->run(fn () => WaitlistEntry::query()->waiting()->where('customer_id', $first->id)->sole());

    actingAs($first)->delete($studio->url('/waitlist/{waitlistEntry}', ['{waitlistEntry}' => $entry->id]))->assertSessionHasNoErrors();
    cancelMemberBooking($studio);

    expect(bookedNames($studio))->toBe(['Waiting 2'])
        ->and(waitingNames($studio))->toBe(['Waiting 3']);
});

it('promotes waiting customers when the capacity goes up', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    waitingCustomers($studio, 3);
    $studio->tenant->update(['timezone' => 'UTC']);

    actingAs($studio->staff)->put($studio->url('/schedule/{classSession}'), [
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
        'starts_at' => now()->addDays(3)->format('Y-m-d').'T18:30',
        'capacity' => 3,
    ])->assertSessionHasNoErrors();

    expect(bookedNames($studio))->toHaveCount(3)->toContain('Waiting 1', 'Waiting 2')
        ->and(waitingNames($studio))->toBe(['Waiting 3']);
});

it('only lets customers join the waitlist of a full session', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/waitlist'))
        ->assertSessionHasErrors(['booking' => 'This class still has places. Book it instead.']);

    expect(waitingNames($studio))->toBe([]);
});

it('refuses to put a customer on the waitlist twice, or when they already hold a place', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->fillSession();
    $url = $studio->url('/schedule/{classSession}/waitlist');

    actingAs($studio->customer)->post($url)->assertSessionHasNoErrors();
    actingAs($studio->customer)->post($url)->assertSessionHasErrors(['booking' => 'You are already on the waitlist for this class.']);
    actingAs($studio->member)->post($url)->assertSessionHasErrors(['booking' => 'You have already booked this class.']);

    expect(waitingNames($studio))->toHaveCount(1);
});

it('refuses to join the waitlist of a session that has started', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->fillSession();
    $studio->tenant->run(fn () => $studio->classSession->update(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]));

    actingAs($studio->customer)->post($studio->url('/schedule/{classSession}/waitlist'))
        ->assertSessionHasErrors(['booking' => 'This class has already started.']);
});

it('shows the waitlist position and offers to join a full session', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    [, $second] = waitingCustomers($studio, 2);

    actingAs($second)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.waitingCount', 2)
            ->where('sessions.0.waitlistPosition', 2)
            ->where('sessions.0.canJoinWaitlist', false));

    actingAs($studio->customer)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.placesLeft', 0)
            ->where('sessions.0.canJoinWaitlist', true)
            ->where('sessions.0.waitlistPosition', null));
});

it('does not let a customer leave another customer waitlist entry', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $entry = $studio->customerWaitlistEntry();

    actingAs($studio->member)->delete($studio->url('/waitlist/{waitlistEntry}', ['{waitlistEntry}' => $entry->id]))->assertForbidden();

    expect(waitingNames($studio))->toHaveCount(1);
});

it('returns 404 for a waitlist entry of another studio', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $theirs = StudioFixture::create($tenants->b)->customerWaitlistEntry();

    actingAs($studio->customer)->delete(tenantUrl($tenants->a, "/waitlist/{$theirs->id}"))->assertNotFound();
});

it('lets the database refuse a second waiting entry but not a released one', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $attributes = ['class_session_id' => $studio->classSession->id, 'customer_id' => $studio->customer->id];

    $studio->tenant->run(function () use ($attributes): void {
        WaitlistEntry::factory()->released()->create($attributes);
        WaitlistEntry::factory()->released()->create($attributes);
        WaitlistEntry::factory()->create($attributes);
    });

    expect(fn () => $studio->tenant->run(fn () => WaitlistEntry::factory()->create($attributes)))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('lets the database refuse a waitlist entry that points at another studio', function (string $column): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);
    $foreignId = $column === 'class_session_id' ? $other->classSession->id : $other->customer->id;

    expect(fn () => $studio->tenant->run(fn () => WaitlistEntry::factory()->create([
        'class_session_id' => $studio->classSession->id,
        'customer_id' => $studio->customer->id,
        $column => $foreignId,
    ])))->toThrow(QueryException::class);
})->with(['class_session_id', 'customer_id']);
