<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('shows the current studio settings to the owner', function (): void {
    $tenants = twoTenants();
    $tenants->a->update(['timezone' => 'Europe/Madrid', 'contact_email' => 'hello@studio-a.test']);

    actingAs(member($tenants->a, Role::Owner))->get(tenantUrl($tenants->a, '/settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Edit')
            ->where('settings', ['name' => 'Studio A', 'timezone' => 'Europe/Madrid', 'contact_email' => 'hello@studio-a.test'])
            ->where('timezones', fn (Collection $timezones): bool => $timezones->contains('Europe/Madrid')));
});

it('saves the settings of the current studio only', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))->put(tenantUrl($tenants->a, '/settings'), [
        'name' => 'Studio A Renamed',
        'timezone' => 'America/New_York',
        'contact_email' => 'front-desk@studio-a.test',
        // Fields the form does not offer are ignored.
        'id' => $tenants->b->id,
        'slug' => 'taken-over',
    ])->assertRedirect(tenantUrl($tenants->a, '/settings'));

    expect($tenants->a->fresh()?->only(['name', 'slug', 'timezone', 'contact_email']))->toBe([
        'name' => 'Studio A Renamed',
        'slug' => 'studio-a',
        'timezone' => 'America/New_York',
        'contact_email' => 'front-desk@studio-a.test',
    ])->and($tenants->b->fresh()?->only(['name', 'slug', 'timezone']))->toBe([
        'name' => 'Studio B',
        'slug' => 'studio-b',
        'timezone' => 'UTC',
    ]);
});

it('rejects an unknown timezone', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))
        ->put(tenantUrl($tenants->a, '/settings'), ['name' => 'Studio A', 'timezone' => 'Mars/Olympus_Mons'])
        ->assertSessionHasErrors('timezone');

    expect($tenants->a->fresh()?->timezone)->toBe('UTC');
});
