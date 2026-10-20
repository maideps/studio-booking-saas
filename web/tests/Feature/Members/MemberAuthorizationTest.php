<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

// Which role may do what is checked for every protected action in
// Authorization/RoleMatrixTest. These tests cover what the member pages do.

it('shares the signed-in user and what the navigation may offer', function (Role $role): void {
    $tenants = twoTenants();
    $user = member($tenants->a, $role);

    actingAs($user)->get(tenantUrl($tenants->a, '/dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.role', $role->value)
            ->where('auth.user.can', [
                'viewMembers' => $role !== Role::Customer,
                'manageClasses' => $role !== Role::Customer,
                'updateSettings' => $role === Role::Owner,
            ])
            ->where('tenant.slug', 'studio-a'));
})->with('roles');

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

it('changes the role when an owner asks', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $bob = member($tenants->a, Role::Customer);

    actingAs($owner)->patch(tenantUrl($tenants->a, "/members/{$bob->id}/role"), ['role' => 'staff'])
        ->assertRedirect(tenantUrl($tenants->a, '/members'));

    expect(roleOf($bob))->toBe(Role::Staff);
});

it('leaves the role alone when staff or a customer asks', function (Role $role): void {
    $tenants = twoTenants();
    $user = member($tenants->a, $role);
    $bob = member($tenants->a, Role::Customer);

    actingAs($user)->patch(tenantUrl($tenants->a, "/members/{$bob->id}/role"), ['role' => 'owner'])->assertForbidden();

    expect(roleOf($bob))->toBe(Role::Customer);
})->with(['staff' => Role::Staff, 'customer' => Role::Customer]);

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
