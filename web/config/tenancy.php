<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Domain
    |--------------------------------------------------------------------------
    |
    | Tenants are served on a subdomain of this domain: the studio with the
    | slug "acme" is https://acme.<domain>. The domain itself is central and
    | serves no tenant routes (D-002). Browsers resolve *.localhost to the
    | local machine, so the default works without DNS changes.
    |
    */

    'domain' => env('TENANCY_DOMAIN', 'localhost'),

];
