<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Requests\Auth\LoginRequest;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ProtectedActions;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows the login page on a tenant subdomain', function (): void {
    $tenants = twoTenants();

    get(tenantUrl($tenants->a, '/login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('tenant', ['name' => 'Studio A', 'slug' => 'studio-a'])
            ->where('auth.user', null));
});

it('returns 404 for the auth pages off a tenant subdomain', function (string $method, string $path): void {
    twoTenants();

    call($method, "http://localhost{$path}")->assertNotFound();
    call($method, "http://studio-c.localhost{$path}")->assertNotFound();
})->with([
    'login page' => ['GET', '/login'],
    'login' => ['POST', '/login'],
    'registration page' => ['GET', '/register'],
    'registration' => ['POST', '/register'],
    'forgot password' => ['POST', '/forgot-password'],
    'reset password' => ['POST', '/reset-password'],
]);

it('signs a member in to their own tenant', function (): void {
    $tenants = twoTenants();
    $alice = member($tenants->a, Role::Staff, ['email' => 'alice@example.com']);

    signIn($tenants->a, 'alice@example.com')->assertRedirect('/dashboard');

    $tenants->a->run(fn () => assertAuthenticatedAs($alice));
});

it('rejects a wrong password', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    signIn($tenants->a, 'alice@example.com', 'wrong-password')->assertSessionHasErrors(['email' => __('auth.failed')]);

    $tenants->a->run(fn () => assertGuest());
});

it('does not sign a tenant A member in to tenant B', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Owner, ['email' => 'alice@example.com']);

    signIn($tenants->b, 'alice@example.com')->assertSessionHasErrors(['email' => __('auth.failed')]);

    $tenants->b->run(fn () => assertGuest());
});

it('signs the same email in to each tenant with that tenant password only', function (): void {
    $tenants = twoTenants();
    $inA = member($tenants->a, Role::Owner, ['email' => 'sam@example.com', 'password' => 'password-for-a']);
    $inB = member($tenants->b, Role::Customer, ['email' => 'sam@example.com', 'password' => 'password-for-b']);

    signIn($tenants->b, 'sam@example.com', 'password-for-a')->assertSessionHasErrors('email');
    signIn($tenants->a, 'sam@example.com', 'password-for-b')->assertSessionHasErrors('email');

    startNewRequest();
    signIn($tenants->b, 'sam@example.com', 'password-for-b')->assertRedirect('/dashboard');
    $tenants->b->run(fn () => assertAuthenticatedAs($inB));

    startNewRequest();
    signIn($tenants->a, 'sam@example.com', 'password-for-a')->assertRedirect('/dashboard');
    $tenants->a->run(fn () => assertAuthenticatedAs($inA));
});

it('locks out repeated failures per tenant', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'sam@example.com']);
    member($tenants->b, Role::Customer, ['email' => 'sam@example.com']);

    foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
        signIn($tenants->a, 'sam@example.com', 'wrong-password')->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // Locked out on tenant A, even with the right password...
    signIn($tenants->a, 'sam@example.com')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many login attempts');
    $tenants->a->run(fn () => assertGuest());

    // ...but the same email on tenant B is a different account.
    startNewRequest();
    signIn($tenants->b, 'sam@example.com')->assertRedirect('/dashboard');
});

it('gives the session a new id on sign-in', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    get(tenantUrl($tenants->a, '/login'));
    $before = session()->getId();

    signIn($tenants->a, 'alice@example.com');

    expect(session()->getId())->not->toBe($before);
});

it('signs a member out', function (Role $role): void {
    $tenants = twoTenants();
    member($tenants->a, $role, ['email' => 'alice@example.com']);
    signIn($tenants->a, 'alice@example.com');

    post(tenantUrl($tenants->a, '/logout'))->assertRedirect(tenantUrl($tenants->a, '/login'));

    $tenants->a->run(fn () => assertGuest());
})->with('roles');

it('sends a guest from a protected page to the tenant login', function (string $method, string $path): void {
    $tenants = twoTenants();
    $bob = member($tenants->a, Role::Customer);

    call($method, tenantUrl($tenants->a, ProtectedActions::path($path, $bob->id)))
        ->assertRedirect(tenantUrl($tenants->a, '/login'));
})->with('protected actions');
