<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Billing\CheckoutKind;
use App\Billing\CheckoutRequest;
use App\Billing\Entitlements;
use App\Billing\PaymentGateway;
use App\Exceptions\BillingRefused;
use App\Models\Checkout;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Starts a purchase of the studio's membership or class pack (D-012). The
 * price comes from the studio's settings, never from the request. Returns the
 * checkout and the hosted page to send the customer to.
 */
final readonly class StartCheckout
{
    public function __construct(
        private TenantContext $context,
        private PaymentGateway $gateway,
        private Entitlements $entitlements,
    ) {}

    /**
     * @return array{checkout: Checkout, url: string}
     *
     * @throws BillingRefused
     */
    public function handle(User $customer, CheckoutKind $kind): array
    {
        $tenant = $this->context->currentOrFail();

        [$amount, $credits, $name] = match ($kind) {
            CheckoutKind::Membership => $tenant->offersMembership()
                ? [(int) $tenant->membership_price_cents, null, "{$tenant->name} monthly membership"]
                : throw BillingRefused::notOffered(),
            CheckoutKind::ClassPack => $tenant->offersClassPack()
                ? [(int) $tenant->class_pack_price_cents, (int) $tenant->class_pack_size, "{$tenant->name} {$tenant->class_pack_size}-class pack"]
                : throw BillingRefused::notOffered(),
        };

        if ($kind === CheckoutKind::Membership && $this->entitlements->activeMembership($customer) !== null) {
            throw BillingRefused::alreadyMember();
        }

        $checkout = new Checkout;
        $checkout->customer()->associate($customer);
        $checkout->kind = $kind;
        $checkout->amount_cents = $amount;
        $checkout->currency = $tenant->currency;
        $checkout->credits = $credits;
        $checkout->save();

        $started = $this->gateway->startCheckout(new CheckoutRequest(
            kind: $kind,
            productName: $name,
            amountCents: $amount,
            currency: $tenant->currency,
            customerEmail: $customer->email,
            successUrl: $tenant->url("/billing/checkouts/{$checkout->id}/return?session_id={CHECKOUT_SESSION_ID}"),
            cancelUrl: $tenant->url('/billing'),
            metadata: ['tenant_id' => (string) $tenant->id, 'checkout_id' => (string) $checkout->id],
        ));

        $checkout->gateway_session_id = $started->sessionId;
        $checkout->save();

        return ['checkout' => $checkout, 'url' => $started->url];
    }
}
