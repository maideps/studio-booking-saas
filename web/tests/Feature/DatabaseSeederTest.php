<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\seed;

it('seeds a demo tenant with one member per role', function (): void {
    seed();

    $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();

    $members = $tenant->run(fn () => User::query()->orderBy('email')->get()
        ->mapWithKeys(fn (User $user): array => [$user->email => $user->role])
        ->all());

    expect($members)->toBe([
        'customer@example.com' => Role::Customer,
        'owner@example.com' => Role::Owner,
        'staff@example.com' => Role::Staff,
    ]);
});
