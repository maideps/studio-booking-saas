<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ClassTemplate;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function classTemplate(array $overrides = []): array
{
    return [
        'name' => 'Morning Flow',
        'description' => 'Wake up gently.',
        'duration_minutes' => 60,
        'capacity' => 12,
        ...$overrides,
    ];
}

/**
 * @return array<int, string>
 */
function templateNames(Tenant $tenant): array
{
    return $tenant->run(fn () => ClassTemplate::query()->orderBy('name')->get()
        ->map(fn (ClassTemplate $template): string => $template->name)
        ->values()->all());
}

it('creates a class template in the current studio', function (Role $role): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, $role))->post(tenantUrl($tenants->a, '/classes'), classTemplate())
        ->assertRedirect(tenantUrl($tenants->a, '/classes'));

    $template = $tenants->a->run(fn () => ClassTemplate::query()->sole());

    expect($template->only(['name', 'description', 'duration_minutes', 'capacity']))->toBe(classTemplate())
        ->and(templateNames($tenants->b))->toBe([]);
})->with(['owner' => Role::Owner, 'staff' => Role::Staff]);

it('keeps class names unique within a studio only', function (): void {
    $tenants = twoTenants();
    $tenants->b->run(fn () => ClassTemplate::factory()->create(['name' => 'Morning Flow']));
    $owner = member($tenants->a, Role::Owner);

    actingAs($owner)->post(tenantUrl($tenants->a, '/classes'), classTemplate())->assertSessionHasNoErrors();
    actingAs($owner)->post(tenantUrl($tenants->a, '/classes'), classTemplate())->assertSessionHasErrors('name');

    expect(templateNames($tenants->a))->toBe(['Morning Flow']);
});

it('updates a class template, keeping its own name', function (): void {
    $tenants = twoTenants();
    $template = $tenants->a->run(fn () => ClassTemplate::factory()->create(['name' => 'Morning Flow']));

    actingAs(member($tenants->a, Role::Staff))
        ->put(tenantUrl($tenants->a, "/classes/{$template->id}"), classTemplate(['capacity' => 20]))
        ->assertSessionHasNoErrors();

    expect($tenants->a->run(fn () => ClassTemplate::query()->findOrFail($template->id)->capacity))->toBe(20);
});

it('rejects invalid durations and capacities', function (string $field, mixed $value): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))
        ->post(tenantUrl($tenants->a, '/classes'), classTemplate([$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    'too short' => ['duration_minutes', 2],
    'too long' => ['duration_minutes', 600],
    'no places' => ['capacity', 0],
    'not a number' => ['capacity', 'many'],
]);

it('returns 404 for another studio class template', function (string $method, string $suffix): void {
    $tenants = twoTenants();
    $theirs = $tenants->b->run(fn () => ClassTemplate::factory()->create(['name' => 'Theirs']));

    actingAs(member($tenants->a, Role::Owner))
        ->call($method, tenantUrl($tenants->a, "/classes/{$theirs->id}{$suffix}"), classTemplate())
        ->assertNotFound();

    expect(templateNames($tenants->b))->toBe(['Theirs']);
})->with([
    'edit page' => ['GET', '/edit'],
    'update' => ['PUT', ''],
]);

it('lists the studio classes to every member', function (Role $role, bool $canManage): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => ClassTemplate::factory()->create(['name' => 'Morning Flow']));
    $tenants->b->run(fn () => ClassTemplate::factory()->create(['name' => 'Theirs']));

    actingAs(member($tenants->a, $role))->get(tenantUrl($tenants->a, '/classes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Classes/Index')
            ->has('templates', 1)
            ->where('templates.0.name', 'Morning Flow')
            ->where('templates.0.canUpdate', $canManage)
            ->where('canCreate', $canManage));
})->with([
    'owner' => [Role::Owner, true],
    'staff' => [Role::Staff, true],
    'customer' => [Role::Customer, false],
]);
