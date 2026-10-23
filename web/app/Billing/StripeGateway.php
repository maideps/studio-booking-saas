<?php

declare(strict_types=1);

namespace App\Billing;

use Carbon\CarbonImmutable;
use Stripe\StripeClient;
use Stripe\Subscription;

/**
 * Stripe Checkout in test mode, through stripe/stripe-php (D-012).
 *
 * Prices are sent inline (price_data), so studios need no products set up in
 * Stripe: a membership is a monthly subscription, a class pack a one-off
 * payment. The tenant and checkout ids travel as metadata on the session and
 * on the subscription or payment, for the feat-008 webhooks.
 */
final readonly class StripeGateway implements PaymentGateway
{
    public function __construct(private StripeClient $stripe) {}

    public function startCheckout(CheckoutRequest $request): StartedCheckout
    {
        $recurring = $request->kind === CheckoutKind::Membership;

        $priceData = [
            'currency' => $request->currency,
            'unit_amount' => $request->amountCents,
            'product_data' => ['name' => $request->productName],
        ];

        if ($recurring) {
            $priceData['recurring'] = ['interval' => 'month'];
        }

        $session = $this->stripe->checkout->sessions->create([
            'mode' => $recurring ? 'subscription' : 'payment',
            'customer_email' => $request->customerEmail,
            'line_items' => [['quantity' => 1, 'price_data' => $priceData]],
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'metadata' => $request->metadata,
            $recurring ? 'subscription_data' : 'payment_intent_data' => ['metadata' => $request->metadata],
        ]);

        return new StartedCheckout($session->id, (string) $session->url);
    }

    public function checkoutResult(string $sessionId): CheckoutResult
    {
        $session = $this->stripe->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription']]);

        $paid = $session->status === 'complete'
            && in_array($session->payment_status, ['paid', 'no_payment_required'], true);

        if (! $paid) {
            return new CheckoutResult(paid: false);
        }

        $subscription = $session->subscription;

        if (! $subscription instanceof Subscription) {
            return new CheckoutResult(paid: true);
        }

        return new CheckoutResult(
            paid: true,
            subscriptionId: $subscription->id,
            periodEndsAt: self::periodEnd($subscription),
        );
    }

    /**
     * Newer Stripe API versions keep the billing period on the subscription
     * items; older ones on the subscription itself. Read both.
     */
    private static function periodEnd(Subscription $subscription): ?CarbonImmutable
    {
        $items = $subscription->items->data;
        $timestamp = $items !== [] && isset($items[0]->current_period_end)
            ? $items[0]->current_period_end
            : $subscription->offsetGet('current_period_end');

        return is_int($timestamp) ? CarbonImmutable::createFromTimestamp($timestamp) : null;
    }
}
