<?php

declare(strict_types=1);

namespace App\Billing;

use Carbon\CarbonImmutable;

/**
 * The gateway's answer about a checkout. For a membership, a paid result also
 * carries the subscription and the end of the paid period.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public bool $paid,
        public ?string $subscriptionId = null,
        public ?CarbonImmutable $periodEndsAt = null,
    ) {}
}
