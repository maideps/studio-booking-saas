<?php

declare(strict_types=1);

use App\Billing\CheckoutKind;
use App\Billing\CheckoutRequest;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Billing\StripeGateway;
use Stripe\StripeClient;
use Tests\Support\Billing\RecordedStripeHttpClient;

// StripeGateway against recorded responses: stripe-php builds and parses
// real requests, but its HTTP client never reaches the network (D-004).

afterEach(fn () => RecordedStripeHttpClient::uninstall());

function stripeGateway(): StripeGateway
{
    return new StripeGateway(new StripeClient('sk_test_fixture'));
}

function checkoutRequest(CheckoutKind $kind): CheckoutRequest
{
    return new CheckoutRequest(
        kind: $kind,
        productName: 'Studio A 10-class pack',
        amountCents: 9000,
        currency: 'eur',
        customerEmail: 'casey@example.com',
        successUrl: 'http://studio-a.localhost/billing/checkouts/1/return?session_id={CHECKOUT_SESSION_ID}',
        cancelUrl: 'http://studio-a.localhost/billing',
        metadata: ['tenant_id' => '1', 'checkout_id' => '1'],
    );
}

it('starts a one-off checkout for a class pack', function (): void {
    $client = RecordedStripeHttpClient::install(['POST /v1/checkout/sessions' => 'checkout_session_created.json']);

    $started = stripeGateway()->startCheckout(checkoutRequest(CheckoutKind::ClassPack));
    $params = $client->requests[0]['params'];

    expect($started->sessionId)->toBe('cs_test_a1B2c3D4e5F6g7H8')
        ->and($started->url)->toStartWith('https://checkout.stripe.com/')
        ->and($params['mode'])->toBe('payment')
        ->and($params['line_items'][0]['price_data'])->toBe([
            'currency' => 'eur',
            'unit_amount' => 9000,
            'product_data' => ['name' => 'Studio A 10-class pack'],
        ])
        ->and($params['success_url'])->toContain('{CHECKOUT_SESSION_ID}')
        ->and($params['metadata'])->toBe(['tenant_id' => '1', 'checkout_id' => '1'])
        ->and($params['payment_intent_data']['metadata'])->toBe(['tenant_id' => '1', 'checkout_id' => '1']);
});

it('starts a monthly subscription checkout for a membership', function (): void {
    $client = RecordedStripeHttpClient::install(['POST /v1/checkout/sessions' => 'checkout_session_created.json']);

    stripeGateway()->startCheckout(checkoutRequest(CheckoutKind::Membership));
    $params = $client->requests[0]['params'];

    expect($params['mode'])->toBe('subscription')
        ->and($params['line_items'][0]['price_data']['recurring'])->toBe(['interval' => 'month'])
        ->and($params['subscription_data']['metadata'])->toBe(['tenant_id' => '1', 'checkout_id' => '1']);
});

it('reports an open checkout as not paid', function (): void {
    RecordedStripeHttpClient::install(['GET /v1/checkout/sessions/cs_test_a1B2c3D4e5F6g7H8' => 'checkout_session_open.json']);

    expect(stripeGateway()->checkoutResult('cs_test_a1B2c3D4e5F6g7H8')->paid)->toBeFalse();
});

it('reports a paid one-off checkout', function (): void {
    RecordedStripeHttpClient::install(['GET /v1/checkout/sessions/cs_test_a1B2c3D4e5F6g7H8' => 'checkout_session_paid_payment.json']);

    $result = stripeGateway()->checkoutResult('cs_test_a1B2c3D4e5F6g7H8');

    expect($result->paid)->toBeTrue()
        ->and($result->subscriptionId)->toBeNull();
});

it('reads the subscription and its period end from a paid membership checkout', function (string $fixture, string $subscriptionId): void {
    $client = RecordedStripeHttpClient::install(['GET /v1/checkout/sessions/cs_test_m1E2m3B4e5R6' => $fixture]);

    $result = stripeGateway()->checkoutResult('cs_test_m1E2m3B4e5R6');

    expect($result->paid)->toBeTrue()
        ->and($result->subscriptionId)->toBe($subscriptionId)
        ->and($result->periodEndsAt?->toIso8601String())->toBe('2026-12-01T00:00:00+00:00')
        ->and($client->requests[0]['params'])->toBe(['expand' => ['subscription']]);
})->with([
    'period on the subscription items' => ['checkout_session_paid_subscription.json', 'sub_1QxYz0ABCDEF'],
    'period on the subscription (older API versions)' => ['checkout_session_paid_subscription_legacy.json', 'sub_1QxYz0LEGACY'],
]);

it('uses the fake gateway in tests, so no test reaches Stripe', function (): void {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('refuses the fake gateway outside local and testing', function (): void {
    app()->forgetInstance(PaymentGateway::class);
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class, 'only allowed in the local and testing');
});

it('uses Stripe when configured', function (): void {
    config(['billing.gateway' => 'stripe', 'billing.stripe.secret' => 'sk_test_fixture']);
    app()->forgetInstance(PaymentGateway::class);

    expect(app(PaymentGateway::class))->toBeInstanceOf(StripeGateway::class);
});
