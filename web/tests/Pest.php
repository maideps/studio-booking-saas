<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\TwoTenants;
use Tests\TestCase;

use function Pest\Laravel\post;

pest()->extend(TestCase::class)->use(LazilyRefreshDatabase::class)->in('Feature');

// Tests that need PostgreSQL (row locks, parallel processes). Their data must be committed
// so other PHP processes can see it, so each test migrates a fresh database instead of
// running inside a transaction. tools/test-postgres.mjs runs them; on SQLite they skip.
pest()->extend(TestCase::class)->use(DatabaseMigrations::class)->group('postgres')->in('Concurrency');

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

/**
 * A user with the role in the tenant. The password is the factory's, "password".
 *
 * @param  array<string, mixed>  $attributes
 */
function member(Tenant $tenant, Role $role = Role::Customer, array $attributes = []): User
{
    return $tenant->run(fn () => User::factory()->create(['role' => $role, ...$attributes]));
}

/**
 * The user's role as stored now, read inside the user's own tenant.
 */
function roleOf(User $user): Role
{
    return $user->tenant()->firstOrFail()->run(fn () => User::query()->findOrFail($user->id)->role);
}

/**
 * Signs in through the real login form on the tenant's subdomain.
 *
 * @return TestResponse<Response>
 */
function signIn(Tenant $tenant, string $email, string $password = 'password', bool $remember = false): TestResponse
{
    return post(tenantUrl($tenant, '/login'), ['email' => $email, 'password' => $password, 'remember' => $remember]);
}

/**
 * Starts the next request the way a new PHP process would.
 *
 * Within one test Laravel keeps the guard's resolved user and the session's
 * attributes between requests. A test that replays a cookie must drop both,
 * or the next request is signed in whatever cookie it sends.
 */
function startNewRequest(): void
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    app()->forgetInstance('redirect');
}
