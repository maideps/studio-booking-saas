<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ProtectedActions;

use function Pest\Laravel\actingAs;

function roleOf(User $user): Role
{
    return $user->tenant()->firstOrFail()->run(fn () => User::query()->findOrFail($user->id)->role);
}

// Each protected action, for each role, a user of another tenant and a guest.
// A guest and a tenant A session replayed on tenant B are covered for every
// action in LoginTest and SessionIsolationTest.

it('shows the dashboard to every role', function (Role $role): void {
    $tenants = twoTenants();
    $user = member($tenants->a, $role);

    actingAs($user)->get(tenantUrl($tenants->a, '/dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.role', $role->value)
            ->where('auth.user.canViewMembers', $role !== Role::Customer)
            ->where('tenant.slug', 'studio-a'));
})->with('roles');

it('lists the members to owners and staff only', function (Role $role, int $status): void {
    $tenants = twoTenants();
    $user = member($tenants->a, $role);

    actingAs($user)->get(tenantUrl($tenants->a, '/members'))->assertStatus($status);
})->with([
    'owner' => [Role::Owner, 200],
    'staff' => [Role::Staff, 200],
    'customer' => [Role::Customer, 403],
]);

it('lists only the members of the current tenant', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner, ['name' => 'Alice']);
    $customer = member($tenants->a, Role::Customer, ['name' => 'Bea']);
    member($tenants->b, Role::Customer, ['name' => 'Carl']);

    actingAs($owner)->get(tenantUrl($tenants->a, '/members'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Members/Index')
            ->has('members', 2)
            ->where('members.0.id', $owner->id)
            ->where('members.0.canUpdateRole', false)
            ->where('members.1.id', $customer->id)
            ->where('members.1.canUpdateRole', true)
            ->has('roles', 3));
});

it('lets only an owner change a role', function (Role $role, int $status, Role $after): void {
    $tenants = twoTenants();
    $user = member($tenants->a, $role);
    $bob = member($tenants->a, Role::Customer);

    actingAs($user)->patch(tenantUrl($tenants->a, "/members/{$bob->id}/role"), ['role' => 'staff'])
        ->assertStatus($status);

    expect(roleOf($bob))->toBe($after);
})->with([
    'owner' => [Role::Owner, 302, Role::Staff],
    'staff' => [Role::Staff, 403, Role::Customer],
    'customer' => [Role::Customer, 403, Role::Customer],
]);

it('does not let an owner change their own role', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);

    actingAs($owner)->patch(tenantUrl($tenants->a, "/members/{$owner->id}/role"), ['role' => 'customer'])
        ->assertForbidden();

    expect(roleOf($owner))->toBe(Role::Owner);
});

it('rejects a role that does not exist', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $bob = member($tenants->a, Role::Customer);

    actingAs($owner)->patch(tenantUrl($tenants->a, "/members/{$bob->id}/role"), ['role' => 'admin'])
        ->assertSessionHasErrors('role');

    expect(roleOf($bob))->toBe(Role::Customer);
});

it('returns 404 for a member of another tenant', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $carl = member($tenants->b, Role::Customer);

    actingAs($owner)->patch(tenantUrl($tenants->a, "/members/{$carl->id}/role"), ['role' => 'owner'])
        ->assertNotFound();

    expect(roleOf($carl))->toBe(Role::Customer);
});

it('denies every authorized action to an owner of another tenant', function (string $method, string $path): void {
    $tenants = twoTenants();
    // Signed in as tenant A's owner while the request runs in tenant B. The
    // guard never returns this user on tenant B (SessionIsolationTest); this
    // checks that the policies refuse it even if it did.
    $foreignOwner = member($tenants->a, Role::Owner);
    $bob = member($tenants->b, Role::Customer);

    actingAs($foreignOwner)
        ->call($method, tenantUrl($tenants->b, ProtectedActions::path($path, $bob->id)), ['role' => 'owner'])
        ->assertForbidden();

    expect(roleOf($bob))->toBe(Role::Customer);
})->with([
    'members list' => ProtectedActions::ALL['members list'],
    'change a role' => ProtectedActions::ALL['change a role'],
]);

it('denies every ability outside a tenant and to users of another tenant', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $bob = member($tenants->a, Role::Customer);

    expect(Gate::forUser($owner)->allows('viewAny', User::class))->toBeFalse()
        ->and($tenants->b->run(fn () => Gate::forUser($owner)->allows('viewAny', User::class)))->toBeFalse()
        ->and($tenants->b->run(fn () => Gate::forUser($owner)->allows('updateRole', $bob)))->toBeFalse()
        ->and($tenants->a->run(fn () => Gate::forUser($owner)->allows('viewAny', User::class)))->toBeTrue()
        ->and($tenants->a->run(fn () => Gate::forUser($owner)->allows('updateRole', $bob)))->toBeTrue();
});

it('covers every route that needs a signed-in user', function (): void {
    app(Kernel::class);
    $router = app('router');

    $protected = collect($router->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => collect($router->gatherRouteMiddleware($route))
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, Authenticate::class)))
        ->map(fn (Route $route): string => collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->implode('|').' /'.$route->uri())
        ->sort()->values()->all();

    $tested = collect(ProtectedActions::ALL)
        ->map(fn (array $action): string => $action[0].' '.$action[1])
        ->sort()->values()->all();

    expect($protected)->toBe($tested);
});
