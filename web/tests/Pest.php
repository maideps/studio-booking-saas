<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\TwoTenants;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(LazilyRefreshDatabase::class)->in('Feature');

/**
 * Two tenants, "studio-a" and "studio-b", for isolation tests.
 */
function twoTenants(): TwoTenants
{
    return TwoTenants::create();
}

/**
 * An absolute URL on the tenant's subdomain.
 */
function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return sprintf('http://%s.%s/%s', $tenant->slug, config()->string('tenancy.domain'), ltrim($path, '/'));
}
