<?php

declare(strict_types=1);

use App\Enums\BookingPayment;
use App\Enums\CreditReason;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;

function giveCredits(StudioFixture $studio, User $customer, int $credits): void
{
    $studio->tenant->run(fn () => CreditEntry::factory()->create(['customer_id' => $customer->id, 'change' => $credits]));
}

function balanceOf(StudioFixture $studio, User $customer): int
{
    return $studio->tenant->run(fn () => (int) CreditEntry::query()->where('customer_id', $customer->id)->sum('change'));
}

/**
 * @return TestResponse<Response>
 */
function bookFixtureSession(StudioFixture $studio, User $customer, ?ClassSession $session = null): TestResponse
{
    $id = ($session ?? $studio->classSession)->id;

    return actingAs($customer)->post(tenantUrl($studio->tenant, "/schedule/{$id}/bookings"));
}

function bookingOf(StudioFixture $studio, User $customer): ?Booking
{
    return $studio->tenant->run(fn () => Booking::query()->active()->where('customer_id', $customer->id)->first());
}

it('books for free at a studio that sells nothing', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);

    bookFixtureSession($studio, $studio->customer)->assertSessionHasNoErrors();

    expect(bookingOf($studio, $studio->customer)?->paid_with)->toBe(BookingPayment::Free);
});

it('refuses a customer with nothing at a studio that sells passes', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();

    bookFixtureSession($studio, $studio->customer)->assertSessionHasErrors(['booking' => 'Buy a membership or a class pack to book.']);

    expect(bookingOf($studio, $studio->customer))->toBeNull();
});

it('books with an active membership without spending credits', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $studio->tenant->run(fn () => Membership::factory()->create(['customer_id' => $studio->customer->id]));
    giveCredits($studio, $studio->customer, 2);

    bookFixtureSession($studio, $studio->customer)->assertSessionHasNoErrors();

    expect(bookingOf($studio, $studio->customer)?->paid_with)->toBe(BookingPayment::Membership)
        ->and(balanceOf($studio, $studio->customer))->toBe(2);
});

it('refuses a cancelled or lapsed membership', function (string $state): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $studio->tenant->run(fn () => Membership::factory()->create([
        'customer_id' => $studio->customer->id,
        ...($state === 'cancelled' ? ['status' => 'cancelled'] : ['current_period_ends_at' => now()->subMinute()]),
    ]));

    bookFixtureSession($studio, $studio->customer)->assertSessionHasErrors('booking');
})->with(['cancelled', 'lapsed']);

it('spends one credit per booking and refunds it on a cancellation in time', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    giveCredits($studio, $studio->customer, 2);

    bookFixtureSession($studio, $studio->customer)->assertSessionHasNoErrors();
    $booking = bookingOf($studio, $studio->customer);

    expect($booking?->paid_with)->toBe(BookingPayment::Credit)
        ->and(balanceOf($studio, $studio->customer))->toBe(1);

    actingAs($studio->customer)->delete($studio->url('/bookings/{booking}', ['{booking}' => (int) $booking?->id]))->assertSessionHasNoErrors();

    expect(balanceOf($studio, $studio->customer))->toBe(2)
        ->and($studio->tenant->run(fn () => CreditEntry::query()->where('booking_id', $booking?->id)->orderBy('id')->pluck('reason')->all()))
        ->toBe([CreditReason::Booking, CreditReason::Refund]);
});

it('spends the last credit once, then refuses', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    giveCredits($studio, $studio->customer, 1);
    $second = $studio->tenant->run(fn () => ClassSession::factory()->create([
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
    ]));

    bookFixtureSession($studio, $studio->customer)->assertSessionHasNoErrors();
    bookFixtureSession($studio, $studio->customer, $second)->assertSessionHasErrors(['booking' => 'Buy a membership or a class pack to book.']);

    expect(balanceOf($studio, $studio->customer))->toBe(0);
});

it('lets only an entitled customer join a waitlist', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $studio->fillSession();
    $url = $studio->url('/schedule/{classSession}/waitlist');

    actingAs($studio->customer)->post($url)->assertSessionHasErrors(['booking' => 'Buy a membership or a class pack to book.']);

    giveCredits($studio, $studio->customer, 1);
    actingAs($studio->customer)->post($url)->assertSessionHasNoErrors();

    // Waiting spends nothing.
    expect(balanceOf($studio, $studio->customer))->toBe(1);
});

it('charges a promoted customer a credit, and skips one who is no longer entitled', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $holder = member($studio->tenant, Role::Customer);
    $first = member($studio->tenant, Role::Customer, ['name' => 'First']);
    $second = member($studio->tenant, Role::Customer, ['name' => 'Second']);
    giveCredits($studio, $holder, 1);
    giveCredits($studio, $second, 1);
    $studio->tenant->run(function () use ($studio, $holder, $first, $second): void {
        $studio->classSession->update(['capacity' => 1]);
        Booking::factory()->create(['class_session_id' => $studio->classSession->id, 'customer_id' => $holder->id, 'paid_with' => BookingPayment::Credit]);
        // First joined while entitled; their credit has since been spent elsewhere.
        WaitlistEntry::factory()->create(['class_session_id' => $studio->classSession->id, 'customer_id' => $first->id]);
        WaitlistEntry::factory()->create(['class_session_id' => $studio->classSession->id, 'customer_id' => $second->id]);
    });
    $holding = bookingOf($studio, $holder);

    actingAs($holder)->delete($studio->url('/bookings/{booking}', ['{booking}' => (int) $holding?->id]))->assertSessionHasNoErrors();

    expect(bookingOf($studio, $first))->toBeNull()
        ->and(bookingOf($studio, $second)?->paid_with)->toBe(BookingPayment::Credit)
        ->and(balanceOf($studio, $second))->toBe(0)
        ->and($studio->tenant->run(fn () => WaitlistEntry::query()->waiting()->count()))->toBe(0);
});
