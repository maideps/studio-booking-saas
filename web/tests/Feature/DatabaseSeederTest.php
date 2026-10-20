<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\seed;

it('seeds a demo tenant with one member per role, classes and upcoming sessions', function (): void {
    seed();

    $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();

    $members = $tenant->run(fn () => User::query()->orderBy('email')->get()
        ->mapWithKeys(fn (User $user): array => [$user->email => $user->role])
        ->all());

    expect($members)->toBe([
        'customer@example.com' => Role::Customer,
        'owner@example.com' => Role::Owner,
        'staff@example.com' => Role::Staff,
    ])
        ->and($tenant->timezone)->toBe('Europe/Madrid')
        ->and($tenant->run(fn () => ClassTemplate::query()->orderBy('name')->pluck('name')->all()))->toBe(['Evening Pilates', 'Morning Flow'])
        ->and($tenant->run(fn () => ClassSession::query()->upcoming()->count()))->toBe(10)
        ->and($tenant->run(fn () => Booking::query()->active()->with('customer')->get()->map(fn (Booking $booking): ?string => $booking->customer?->email)->all()))
        ->toBe(['customer@example.com']);
});
