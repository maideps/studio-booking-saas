<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

// "Now" is fixed in winter, so Europe/Madrid is UTC+1.
beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-11-02 09:00:00', 'UTC')));

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sessionPayload(StudioFixture $studio, array $overrides = []): array
{
    return [
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
        'starts_at' => '2026-11-10T18:30',
        'capacity' => 8,
        ...$overrides,
    ];
}

function storedSession(StudioFixture $studio): ClassSession
{
    return $studio->tenant->run(fn () => ClassSession::query()->latest('id')->firstOrFail());
}

it('stores the session start in UTC and ends it after the class duration', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->update(['timezone' => 'Europe/Madrid']);
    $studio->tenant->run(fn () => $studio->classTemplate->update(['duration_minutes' => 75]));

    actingAs($studio->owner)->post($studio->url('/schedule'), sessionPayload($studio))
        ->assertRedirect($studio->url('/schedule'));

    $session = storedSession($studio);

    expect($session->starts_at->toIso8601String())->toBe('2026-11-10T17:30:00+00:00')
        ->and($session->ends_at->toIso8601String())->toBe('2026-11-10T18:45:00+00:00')
        ->and($session->capacity)->toBe(8)
        ->and($session->instructor_id)->toBe($studio->staff->id);
});

it('shows the schedule in the studio timezone, to every member', function (Role $role): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->update(['timezone' => 'Europe/Madrid']);
    $studio->tenant->run(fn () => $studio->classSession->update([
        'starts_at' => '2026-11-10 17:30:00',
        'ends_at' => '2026-11-10 18:30:00',
    ]));

    actingAs($studio->user($role))->get($studio->url('/schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Schedule/Index')
            ->where('timezone', 'Europe/Madrid')
            ->has('sessions', 1)
            ->where('sessions.0.startsAt', '2026-11-10T18:30')
            ->where('sessions.0.endsAt', '2026-11-10T19:30')
            ->where('sessions.0.className', $studio->classTemplate->name)
            ->where('sessions.0.instructorName', $studio->staff->name)
            ->where('sessions.0.canUpdate', $role !== Role::Customer));
})->with('roles');

it('lists only upcoming sessions of the current studio', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    StudioFixture::create($tenants->b);
    $studio->tenant->run(fn () => ClassSession::factory()->create([
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]));

    actingAs($studio->customer)->get($studio->url('/schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.id', $studio->classSession->id));
});

it('reschedules a session in the studio timezone', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->update(['timezone' => 'America/New_York']);

    actingAs($studio->staff)
        ->get($studio->url('/schedule/{classSession}/edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Schedule/Form')
            ->where('session.id', $studio->classSession->id)
            ->where('timezone', 'America/New_York'));

    actingAs($studio->staff)
        ->put($studio->url('/schedule/{classSession}'), sessionPayload($studio, ['starts_at' => '2026-11-12T07:00', 'capacity' => 4]))
        ->assertSessionHasNoErrors();

    $session = $studio->tenant->run(fn () => ClassSession::query()->findOrFail($studio->classSession->id));

    expect($session->starts_at->toIso8601String())->toBe('2026-11-12T12:00:00+00:00')
        ->and($session->capacity)->toBe(4);
});

it('rejects a session that refers to another studio, a customer instructor or a past time', function (string $field, Closure $value): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);

    actingAs($studio->owner)
        ->post($studio->url('/schedule'), sessionPayload($studio, [$field => $value($studio, $other)]))
        ->assertSessionHasErrors($field);

    expect($studio->tenant->run(fn () => ClassSession::query()->count()))->toBe(1);
})->with([
    'another studio template' => ['class_template_id', fn (StudioFixture $studio, StudioFixture $other): int => $other->classTemplate->id],
    'another studio instructor' => ['instructor_id', fn (StudioFixture $studio, StudioFixture $other): int => $other->staff->id],
    'a customer as instructor' => ['instructor_id', fn (StudioFixture $studio, StudioFixture $other): int => $studio->customer->id],
    'a past start' => ['starts_at', fn (): string => '2026-11-01T18:30'],
    'not a time' => ['starts_at', fn (): string => 'next tuesday'],
]);

it('returns 404 for another studio session', function (string $method, string $suffix): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);

    actingAs($studio->owner)
        ->call($method, tenantUrl($tenants->a, "/schedule/{$other->classSession->id}{$suffix}"), sessionPayload($studio))
        ->assertNotFound();
})->with([
    'edit page' => ['GET', '/edit'],
    'update' => ['PUT', ''],
]);

it('refuses a cross-studio reference in the database, even without validation', function (string $column): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    $other = StudioFixture::create($tenants->b);
    $foreignId = $column === 'class_template_id' ? $other->classTemplate->id : $other->staff->id;

    expect(fn () => $studio->tenant->run(fn () => ClassSession::factory()->create([
        'class_template_id' => $studio->classTemplate->id,
        'instructor_id' => $studio->staff->id,
        $column => $foreignId,
    ])))->toThrow(QueryException::class);
})->with(['class_template_id', 'instructor_id']);

it('offers only this studio templates and staff in the session form', function (): void {
    $tenants = twoTenants();
    $studio = StudioFixture::create($tenants->a);
    StudioFixture::create($tenants->b);

    actingAs($studio->owner)->get($studio->url('/schedule/create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Schedule/Form')
            ->where('session', null)
            ->where('templates', [['id' => $studio->classTemplate->id, 'name' => $studio->classTemplate->name, 'capacity' => 12]])
            ->where('instructors', fn (Collection $instructors): bool => $instructors->pluck('id')->sort()->values()->all()
                === collect([$studio->owner->id, $studio->staff->id])->sort()->values()->all()));
});

it('converts studio wall-clock time to UTC and back', function (): void {
    $tenant = twoTenants()->a;
    $tenant->update(['timezone' => 'Asia/Kolkata']);

    $utc = $tenant->parseLocalTime('2026-11-10T09:15');

    expect($utc->toIso8601String())->toBe('2026-11-10T03:45:00+00:00')
        ->and($tenant->localTime($utc)->format('Y-m-d\TH:i'))->toBe('2026-11-10T09:15')
        ->and(fn () => $tenant->parseLocalTime('10/11/2026'))->toThrow(InvalidArgumentException::class);
});

it('keeps an existing staff member as instructor only while they are staff', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $studio->tenant->run(fn () => User::query()->findOrFail($studio->staff->id)->forceFill(['role' => Role::Customer])->save());

    actingAs($studio->owner)
        ->put($studio->url('/schedule/{classSession}'), sessionPayload($studio))
        ->assertSessionHasErrors('instructor_id');
});
