<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array<string, string>
 */
function registration(string $email = 'new@example.com'): array
{
    return [
        'name' => 'New Member',
        'email' => $email,
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
    ];
}

it('shows the registration page on a tenant subdomain', function (): void {
    $tenants = twoTenants();

    get(tenantUrl($tenants->a, '/register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('registers a customer in the current tenant and signs them in', function (): void {
    $tenants = twoTenants();

    post(tenantUrl($tenants->a, '/register'), registration())->assertRedirect(tenantUrl($tenants->a, '/dashboard'));

    $user = $tenants->a->run(fn () => User::query()->where('email', 'new@example.com')->sole());

    expect($user->role)->toBe(Role::Customer)
        ->and($user->tenant_id)->toBe($tenants->a->id)
        ->and($tenants->b->run(fn () => User::query()->count()))->toBe(0);
    $tenants->a->run(fn () => assertAuthenticatedAs($user));
});

it('registers an email that already exists in another tenant', function (): void {
    $tenants = twoTenants();
    member($tenants->b, Role::Owner, ['email' => 'sam@example.com']);

    post(tenantUrl($tenants->a, '/register'), registration('sam@example.com'))->assertSessionHasNoErrors();

    expect($tenants->a->run(fn () => User::query()->where('email', 'sam@example.com')->count()))->toBe(1)
        ->and($tenants->b->run(fn () => User::query()->where('email', 'sam@example.com')->count()))->toBe(1);
});

it('rejects an email that already exists in the same tenant', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'sam@example.com']);

    post(tenantUrl($tenants->a, '/register'), registration('sam@example.com'))->assertSessionHasErrors('email');

    expect($tenants->a->run(fn () => User::query()->count()))->toBe(1);
    $tenants->a->run(fn () => assertGuest());
});

it('never lets a registration choose its role', function (string $role): void {
    $tenants = twoTenants();

    post(tenantUrl($tenants->a, '/register'), [...registration(), 'role' => $role, 'tenant_id' => $tenants->b->id])
        ->assertSessionHasNoErrors();

    $user = $tenants->a->run(fn () => User::query()->where('email', 'new@example.com')->sole());

    expect($user->role)->toBe(Role::Customer)
        ->and($user->tenant_id)->toBe($tenants->a->id);
})->with(['owner', 'staff']);
