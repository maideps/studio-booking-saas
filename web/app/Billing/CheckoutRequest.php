<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * One hosted checkout to start: a monthly membership (recurring) or a class
 * pack (one-off). The metadata travels with the session so feat-008 webhooks
 * can find the tenant and the checkout again.
 */
final readonly class CheckoutRequest
{
    /**
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public CheckoutKind $kind,
        public string $productName,
        public int $amountCents,
        public string $currency,
        public string $customerEmail,
        public string $successUrl,
        public string $cancelUrl,
        public array $metadata,
    ) {}
}
