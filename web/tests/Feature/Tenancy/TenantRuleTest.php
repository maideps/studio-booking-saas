<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantRule;
use Illuminate\Support\Facades\Validator;

it('checks unique only against the current tenant', function (): void {
    $tenants = twoTenants();
    member($tenants->b, Role::Customer, ['email' => 'sam@example.com']);

    $passesIn = fn (Tenant $tenant): bool => $tenant->run(fn () => Validator::make(
        ['email' => 'sam@example.com'],
        ['email' => [TenantRule::unique(User::class, 'email')]],
    )->passes());

    expect($passesIn($tenants->a))->toBeTrue()
        ->and($passesIn($tenants->b))->toBeFalse();
});

it('checks exists only against the current tenant', function (): void {
    $tenants = twoTenants();
    $carl = member($tenants->b, Role::Customer);

    $passesIn = fn (Tenant $tenant): bool => $tenant->run(fn () => Validator::make(
        ['member_id' => $carl->id],
        ['member_id' => [TenantRule::exists(User::class, 'id')]],
    )->passes());

    expect($passesIn($tenants->a))->toBeFalse()
        ->and($passesIn($tenants->b))->toBeTrue();
});

it('refuses to build a rule outside a tenant or for a central model', function (): void {
    expect(fn () => TenantRule::unique(User::class, 'email'))->toThrow(TenancyViolation::class)
        ->and(fn () => TenantRule::exists(Tenant::class, 'id'))->toThrow(InvalidArgumentException::class);
});

it('builds tenant URLs from APP_URL', function (): void {
    config(['app.url' => 'https://example.test:8443', 'tenancy.domain' => 'studios.test']);
    $tenant = Tenant::factory()->make(['slug' => 'acme']);

    expect($tenant->url('/login'))->toBe('https://acme.studios.test:8443/login')
        ->and($tenant->url())->toBe('https://acme.studios.test:8443/');
});
