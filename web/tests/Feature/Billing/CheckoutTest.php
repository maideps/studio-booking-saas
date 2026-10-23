<?php

declare(strict_types=1);

use App\Billing\CheckoutKind;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Enums\CheckoutStatus;
use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\Checkout;
use App\Models\CreditEntry;
use App\Models\Membership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;

/**
 * The gateway the app uses in tests. The return type fails the test if it is
 * ever anything but the fake.
 */
function fakeGateway(): FakePaymentGateway
{
    return app(PaymentGateway::class);
}

function lastCheckout(StudioFixture $studio): Checkout
{
    return $studio->tenant->run(fn () => Checkout::query()->latest('id')->firstOrFail());
}

function creditsOf(StudioFixture $studio, User $customer): int
{
    return $studio->tenant->run(fn () => (int) CreditEntry::query()->where('customer_id', $customer->id)->sum('change'));
}

it('starts a class-pack checkout at the studio price, whatever the request says', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();

    $response = actingAs($studio->customer)
        ->post($studio->url('/billing/checkout/class_pack'), ['amount_cents' => 1, 'credits' => 999]);

    $checkout = lastCheckout($studio);
    $request = fakeGateway()->requestFor((string) $checkout->gateway_session_id);

    $response->assertRedirect('https://checkout.fake.test/'.$checkout->gateway_session_id);
    expect($checkout->status)->toBe(CheckoutStatus::Pending)
        ->and($checkout->kind)->toBe(CheckoutKind::ClassPack)
        ->and($checkout->amount_cents)->toBe(9000)
        ->and($checkout->credits)->toBe(10)
        ->and($checkout->customer_id)->toBe($studio->customer->id)
        ->and($request->amountCents)->toBe(9000)
        ->and($request->currency)->toBe('eur')
        ->and($request->customerEmail)->toBe($studio->customer->email)
        ->and($request->successUrl)->toBe($studio->tenant->url("/billing/checkouts/{$checkout->id}/return?session_id={CHECKOUT_SESSION_ID}"))
        ->and($request->metadata)->toBe(['tenant_id' => (string) $studio->tenant->id, 'checkout_id' => (string) $checkout->id]);
});

it('refuses what the studio does not sell', function (string $kind): void {
    $studio = StudioFixture::create(twoTenants()->a);

    actingAs($studio->customer)->post($studio->url("/billing/checkout/{$kind}"))
        ->assertRedirect($studio->url('/billing'))
        ->assertSessionHasErrors(['billing' => 'This studio does not sell that.']);

    expect(fakeGateway()->sessionIds())->toBe([]);
})->with(['membership', 'class_pack']);

it('returns 404 for an unknown kind of purchase', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();

    actingAs($studio->customer)->post($studio->url('/billing/checkout/lifetime'))->assertNotFound();
});

it('adds the pack credits once the gateway reports the checkout paid', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    actingAs($studio->customer)->post($studio->url('/billing/checkout/class_pack'));
    $checkout = lastCheckout($studio);
    $returnUrl = $studio->url("/billing/checkouts/{$checkout->id}/return");

    // Back from the gateway before paying: nothing changes.
    actingAs($studio->customer)->get($returnUrl)->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'not received'));
    expect(creditsOf($studio, $studio->customer))->toBe(0);

    fakeGateway()->markPaid((string) $checkout->gateway_session_id);

    actingAs($studio->customer)->get($returnUrl)->assertSessionHas('status', 'Payment received. Thank you!');
    actingAs($studio->customer)->get($returnUrl);

    expect(creditsOf($studio, $studio->customer))->toBe(10)
        ->and(lastCheckout($studio)->status)->toBe(CheckoutStatus::Completed);
});

it('activates a membership once the gateway reports the checkout paid', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    actingAs($studio->customer)->post($studio->url('/billing/checkout/membership'));
    $checkout = lastCheckout($studio);
    fakeGateway()->markPaid((string) $checkout->gateway_session_id);

    actingAs($studio->customer)->get($studio->url("/billing/checkouts/{$checkout->id}/return"));

    $membership = $studio->tenant->run(fn () => Membership::query()->sole());

    expect($membership->customer_id)->toBe($studio->customer->id)
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->coversBookings())->toBeTrue()
        ->and($membership->checkout_id)->toBe($checkout->id)
        ->and($membership->gateway_subscription_id)->toStartWith('sub_fake_');

    actingAs($studio->customer)->post($studio->url('/billing/checkout/membership'))
        ->assertSessionHasErrors(['billing' => 'You already have an active membership.']);
});

it('does not let one customer complete another customer checkout', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $checkout = $studio->customerPaidCheckout();
    $other = member($studio->tenant, Role::Customer);

    actingAs($other)->get($studio->url("/billing/checkouts/{$checkout->id}/return"))->assertForbidden();

    expect(creditsOf($studio, $studio->customer))->toBe(0)
        ->and(creditsOf($studio, $other))->toBe(0);
});

it('returns 404 for a checkout of another studio', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $theirs = StudioFixture::create($tenants->b)->customerPaidCheckout();

    actingAs($studio->customer)->get(tenantUrl($tenants->a, "/billing/checkouts/{$theirs->id}/return"))->assertNotFound();
});

it('shows the offers, the membership and the credit balance', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->sellPasses();
    $studio->tenant->run(fn () => CreditEntry::factory()->create(['customer_id' => $studio->customer->id, 'change' => 3]));

    actingAs($studio->customer)->get($studio->url('/billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Show')
            ->where('offers', [
                'currency' => 'eur',
                'membershipPriceCents' => 4900,
                'classPackSize' => 10,
                'classPackPriceCents' => 9000,
                'freeToBook' => false,
            ])
            ->where('membership', null)
            ->where('credits', 3));
});
