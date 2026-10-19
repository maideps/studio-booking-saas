<?php

declare(strict_types=1);

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

// Probe routes behind the real `tenant` middleware; the product has no
// tenant routes of its own yet.
beforeEach(function (): void {
    Route::middleware(['web', 'tenant'])->group(function (): void {
        Route::get('/_tenancy/users', fn () => User::query()->orderBy('email')->pluck('email'));
        Route::get('/_tenancy/users/{user}', fn (User $user) => ['email' => $user->email]);
    });
});

it('serves a record on its own tenant subdomain', function (): void {
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create(['email' => 'alice@example.com']));

    getJson(tenantUrl($tenants->a, "/_tenancy/users/{$alice->id}"))
        ->assertOk()
        ->assertExactJson(['email' => 'alice@example.com']);
});

it('returns 404 for a tenant A record on tenant B subdomain', function (): void {
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create());

    getJson(tenantUrl($tenants->b, "/_tenancy/users/{$alice->id}"))->assertNotFound();
});

it('lists only the current tenant records', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->create(['email' => 'alice@example.com']));
    $tenants->b->run(fn () => User::factory()->create(['email' => 'bob@example.com']));

    getJson(tenantUrl($tenants->b, '/_tenancy/users'))
        ->assertOk()
        ->assertExactJson(['bob@example.com']);
});

it('returns 404 when the host is not a known tenant subdomain', function (string $host): void {
    twoTenants();

    get("http://{$host}/_tenancy/users")->assertNotFound();
})->with([
    'unknown tenant' => 'studio-c.localhost',
    'central domain' => 'localhost',
    'nested subdomain' => 'www.studio-a.localhost',
    'other domain' => 'studio-a.example.com',
    'domain suffix lookalike' => 'studio-a.notlocalhost',
]);

it('leaves no tenant behind after the request', function (): void {
    $tenants = twoTenants();

    getJson(tenantUrl($tenants->a, '/_tenancy/users'))->assertOk();

    expect(app(TenantContext::class)->current())->toBeNull();
});
