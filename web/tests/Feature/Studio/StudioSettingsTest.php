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
            ->where('settings', [
                'name' => 'Studio A',
                'timezone' => 'Europe/Madrid',
                'contact_email' => 'hello@studio-a.test',
                'cancellation_window_hours' => 12,
                'currency' => 'eur',
                'membership_price' => null,
                'class_pack_size' => null,
                'class_pack_price' => null,
            ])
            ->where('timezones', fn (Collection $timezones): bool => $timezones->contains('Europe/Madrid')));
});

it('saves the settings of the current studio only', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))->put(tenantUrl($tenants->a, '/settings'), [
        'name' => 'Studio A Renamed',
        'timezone' => 'America/New_York',
        'contact_email' => 'front-desk@studio-a.test',
        'cancellation_window_hours' => 24,
        // Fields the form does not offer are ignored.
        'id' => $tenants->b->id,
        'slug' => 'taken-over',
    ])->assertRedirect(tenantUrl($tenants->a, '/settings'));

    expect($tenants->a->fresh()?->only(['name', 'slug', 'timezone', 'contact_email', 'cancellation_window_hours']))->toBe([
        'name' => 'Studio A Renamed',
        'slug' => 'studio-a',
        'timezone' => 'America/New_York',
        'contact_email' => 'front-desk@studio-a.test',
        'cancellation_window_hours' => 24,
    ])->and($tenants->b->fresh()?->only(['name', 'slug', 'timezone']))->toBe([
        'name' => 'Studio B',
        'slug' => 'studio-b',
        'timezone' => 'UTC',
    ]);
});

it('saves the studio prices in cents, and clears them', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $settings = ['name' => 'Studio A', 'timezone' => 'UTC', 'cancellation_window_hours' => 12, 'currency' => 'gbp'];

    actingAs($owner)->put(tenantUrl($tenants->a, '/settings'), [
        ...$settings,
        'membership_price' => '49.99',
        'class_pack_size' => 10,
        'class_pack_price' => '90',
    ])->assertSessionHasNoErrors();

    expect($tenants->a->fresh()?->only(['currency', 'membership_price_cents', 'class_pack_size', 'class_pack_price_cents']))->toBe([
        'currency' => 'gbp',
        'membership_price_cents' => 4999,
        'class_pack_size' => 10,
        'class_pack_price_cents' => 9000,
    ]);

    actingAs($owner)->put(tenantUrl($tenants->a, '/settings'), [
        ...$settings,
        'membership_price' => '',
        'class_pack_size' => '',
        'class_pack_price' => '',
    ])->assertSessionHasNoErrors();

    expect($tenants->a->fresh()?->isFreeToBook())->toBeTrue();
});

it('rejects incomplete or invalid prices', function (array $prices, string $field): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))->put(tenantUrl($tenants->a, '/settings'), [
        'name' => 'Studio A',
        'timezone' => 'UTC',
        'cancellation_window_hours' => 12,
        ...$prices,
    ])->assertSessionHasErrors($field);
})->with([
    'pack size without a price' => [['class_pack_size' => 10], 'class_pack_price'],
    'pack price without a size' => [['class_pack_price' => '90'], 'class_pack_size'],
    'fractions of a cent' => [['membership_price' => '49.999'], 'membership_price'],
    'unknown currency' => [['currency' => 'btc'], 'currency'],
]);

it('rejects an unknown timezone', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))
        ->put(tenantUrl($tenants->a, '/settings'), ['name' => 'Studio A', 'timezone' => 'Mars/Olympus_Mons', 'cancellation_window_hours' => 12])
        ->assertSessionHasErrors('timezone');

    expect($tenants->a->fresh()?->timezone)->toBe('UTC');
});
