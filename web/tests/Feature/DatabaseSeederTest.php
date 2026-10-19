<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\seed;

it('seeds a demo tenant with a user inside it', function (): void {
    seed();

    $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();

    expect($tenant->run(fn () => User::query()->pluck('email')->all()))->toBe(['test@example.com']);
});
