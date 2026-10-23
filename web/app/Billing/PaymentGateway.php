<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * The payment provider, as the domain needs it (D-004, D-012). The app never
 * talks to Stripe directly: StripeGateway implements this with
 * stripe/stripe-php, and FakePaymentGateway implements it for tests and the
 * local demo.
 */
interface PaymentGateway
{
    /**
     * Starts a hosted checkout and returns where to send the customer.
     */
    public function startCheckout(CheckoutRequest $request): StartedCheckout;

    /**
     * What happened to a checkout started earlier.
     */
    public function checkoutResult(string $sessionId): CheckoutResult;
}
