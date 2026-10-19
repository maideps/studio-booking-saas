<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CrossTenant;
use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;

it('hides tenant A records from tenant B queries', function (): void {
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create(['email' => 'alice@example.com']));
    $tenants->a->run(fn () => User::factory()->create());
    $tenants->b->run(fn () => User::factory()->create());

    $tenants->b->run(function () use ($alice): void {
        expect(User::query()->count())->toBe(1)
            ->and(User::query()->find($alice->id))->toBeNull()
            ->and(User::query()->where('email', 'alice@example.com')->exists())->toBeFalse()
            ->and(User::query()->whereKey($alice->id)->update(['name' => 'Changed']))->toBe(0)
            ->and(User::query()->whereKey($alice->id)->delete())->toBe(0);
    });

    $tenants->a->run(function () use ($alice): void {
        expect(User::query()->count())->toBe(2)
            ->and(User::query()->find($alice->id)?->name)->toBe($alice->name);
    });
});

it('fills tenant_id from the current tenant on create', function (): void {
    $tenants = twoTenants();

    $user = $tenants->b->run(fn () => User::factory()->create());

    expect($user->tenant_id)->toBe($tenants->b->id)
        ->and($user->tenant()->is($tenants->b))->toBeTrue();
});

it('ignores a mass-assigned tenant_id', function (): void {
    $tenants = twoTenants();

    $user = $tenants->a->run(fn () => User::query()->create([
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'secret-password',
        'tenant_id' => $tenants->b->id,
    ]));

    expect($user->tenant_id)->toBe($tenants->a->id);
});

it('allows the same email once per tenant', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->create(['email' => 'shared@example.com']));

    $other = $tenants->b->run(fn () => User::factory()->create(['email' => 'shared@example.com']));

    expect($other->exists)->toBeTrue();
    expect(fn () => $tenants->a->run(fn () => User::factory()->create(['email' => 'shared@example.com'])))
        ->toThrow(QueryException::class);
});

it('refuses to query tenant-owned models without a tenant', function (): void {
    twoTenants();

    expect(fn () => User::query()->count())->toThrow(TenancyViolation::class);
});

it('refuses to create tenant-owned records without a tenant', function (): void {
    expect(fn () => User::factory()->create())->toThrow(TenancyViolation::class);
});

it('refuses to create a record for another tenant', function (): void {
    $tenants = twoTenants();

    expect(fn () => $tenants->a->run(
        fn () => User::factory()->make()->forceFill(['tenant_id' => $tenants->b->id])->save(),
    ))->toThrow(TenancyViolation::class);
});

it('refuses to move a record to another tenant', function (): void {
    $tenants = twoTenants();
    $user = $tenants->a->run(fn () => User::factory()->create());

    expect(fn () => $tenants->a->run(fn () => $user->forceFill(['tenant_id' => $tenants->b->id])->save()))
        ->toThrow(TenancyViolation::class);
});

it('refuses to write a tenant A record from tenant B', function (): void {
    $tenants = twoTenants();
    $user = $tenants->a->run(fn () => User::factory()->create(['name' => 'Original']));

    expect(fn () => $tenants->b->run(fn () => $user->update(['name' => 'Changed'])))
        ->toThrow(TenancyViolation::class)
        ->and(fn () => $tenants->b->run(fn () => $user->delete()))
        ->toThrow(TenancyViolation::class)
        ->and(fn () => $user->update(['name' => 'Changed']))
        ->toThrow(TenancyViolation::class);

    expect($tenants->a->run(fn () => User::query()->find($user->id)?->name))->toBe('Original');
});

it('refuses a bulk update that moves records to another tenant', function (string $column): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->count(2)->create());

    expect(fn () => $tenants->a->run(fn () => User::query()->update([$column => $tenants->b->id])))
        ->toThrow(TenancyViolation::class)
        ->and(fn () => $tenants->a->run(fn () => User::query()->increment('tenant_id')))
        ->toThrow(TenancyViolation::class)
        ->and(fn () => $tenants->a->run(fn () => User::query()->incrementEach(['tenant_id' => 1])))
        ->toThrow(TenancyViolation::class);

    expect($tenants->a->run(fn () => User::query()->count()))->toBe(2)
        ->and($tenants->b->run(fn () => User::query()->count()))->toBe(0);
})->with(['tenant_id', 'users.tenant_id']);

it('keeps bulk updates inside the current tenant', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->create(['name' => 'Alice']));
    $tenants->b->run(fn () => User::factory()->create(['name' => 'Bob']));

    $updated = $tenants->b->run(fn () => User::query()->update(['name' => 'Renamed']));

    expect($updated)->toBe(1)
        ->and($tenants->a->run(fn () => User::query()->value('name')))->toBe('Alice');
});

it('scopes relations to the current tenant', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->count(2)->create());
    $tenants->b->run(fn () => User::factory()->create());

    // A relation from tenant A's row still runs through the scope of the current tenant.
    expect($tenants->a->run(fn () => $tenants->a->hasMany(User::class)->count()))->toBe(2)
        ->and($tenants->b->run(fn () => $tenants->a->hasMany(User::class)->count()))->toBe(0)
        ->and($tenants->b->run(fn () => User::query()->whereHas('tenant', fn ($query) => $query->whereKey($tenants->a->id))->count()))->toBe(0);
});

it('refuses to run inside a tenant that was never saved', function (): void {
    expect(fn () => Tenant::factory()->make()->run(fn () => null))->toThrow(TenancyViolation::class);
});

it('reads across tenants only through CrossTenant', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => User::factory()->count(2)->create());
    $tenants->b->run(fn () => User::factory()->create());

    expect(CrossTenant::query(User::class)->count())->toBe(3)
        ->and($tenants->b->run(fn () => CrossTenant::query(User::class)->count()))->toBe(3);
});

it('refuses CrossTenant for a model that is not tenant-owned', function (): void {
    expect(fn () => CrossTenant::query(Tenant::class))->toThrow(InvalidArgumentException::class);
});

it('restores the previous tenant after run, even when the callback throws', function (): void {
    $tenants = twoTenants();
    $context = app(TenantContext::class);

    $tenants->a->run(function () use ($tenants, $context): void {
        $tenants->b->run(fn () => expect($context->current()?->is($tenants->b))->toBeTrue());

        expect($context->current()?->is($tenants->a))->toBeTrue();

        try {
            $tenants->b->run(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        expect($context->current()?->is($tenants->a))->toBeTrue();
    });

    expect($context->current())->toBeNull();
});
