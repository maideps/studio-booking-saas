<?php

declare(strict_types=1);

namespace App\Billing;

final readonly class StartedCheckout
{
    public function __construct(
        public string $sessionId,
        public string $url,
    ) {}
}
