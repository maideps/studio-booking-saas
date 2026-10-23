<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment gateway (D-004, D-012)
    |--------------------------------------------------------------------------
    |
    | "stripe" uses Stripe Checkout in test mode through stripe/stripe-php.
    | "fake" keeps checkouts in memory and pays them at once; it exists for
    | tests and for a local demo without a Stripe key, and is refused in any
    | other environment. By default the fake is used when no key is set.
    |
    */

    'gateway' => env('BILLING_GATEWAY', env('STRIPE_SECRET') ? 'stripe' : 'fake'),

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
    ],

];
