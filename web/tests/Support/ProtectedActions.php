<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Role;
use Closure;

/**
 * Every route that needs a signed-in user, with the roles allowed to use it
 * and a valid payload. Paths use the placeholders of StudioFixture::path().
 *
 * The tests run each action for every role, for a guest and for a user of
 * another tenant. A test fails when a route with `auth` middleware is
 * missing from this list, so a new protected route cannot skip them.
 */
final readonly class ProtectedActions
{
    /**
     * @param  list<Role>  $allowed
     * @param  bool  $authorized  whether a policy guards it, so a user of another tenant gets 403
     * @param  Closure(StudioFixture): array<string, mixed>  $payload
     * @param  (Closure(StudioFixture): array<string, int|string>)|null  $setup  creates the records the action needs
     *                                                                           and returns extra path placeholders
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $allowed,
        public bool $authorized,
        public Closure $payload,
        public ?Closure $setup = null,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        $everyone = Role::cases();
        $managers = [Role::Owner, Role::Staff];
        $owner = [Role::Owner];
        $none = fn (StudioFixture $studio): array => [];

        $classTemplate = fn (StudioFixture $studio): array => [
            'name' => 'Evening Flow',
            'description' => 'A slow evening class.',
            'duration_minutes' => 75,
            'capacity' => 10,
        ];
        $classSession = fn (StudioFixture $studio): array => [
            'class_template_id' => $studio->classTemplate->id,
            'instructor_id' => $studio->staff->id,
            'starts_at' => now()->addDays(3)->format('Y-m-d').'T18:30',
            'capacity' => 8,
        ];

        return [
            'dashboard' => new self('GET', '/dashboard', $everyone, false, $none),
            'log out' => new self('POST', '/logout', $everyone, false, $none),
            'members list' => new self('GET', '/members', $managers, true, $none),
            'change a role' => new self('PATCH', '/members/{member}/role', $owner, true, fn (StudioFixture $studio): array => ['role' => 'staff']),
            'invite a member' => new self('POST', '/invitations', $owner, true, fn (StudioFixture $studio): array => ['email' => 'new.staff@example.com', 'role' => 'staff']),
            'revoke an invitation' => new self('DELETE', '/invitations/{invitation}', $owner, true, $none),
            'settings page' => new self('GET', '/settings', $owner, true, $none),
            'save settings' => new self('PUT', '/settings', $owner, true, fn (StudioFixture $studio): array => [
                'name' => 'Renamed Studio',
                'timezone' => 'Europe/Madrid',
                'contact_email' => 'hello@example.com',
                'cancellation_window_hours' => 6,
            ]),
            'classes list' => new self('GET', '/classes', $everyone, true, $none),
            'new class page' => new self('GET', '/classes/create', $managers, true, $none),
            'create a class' => new self('POST', '/classes', $managers, true, $classTemplate),
            'edit class page' => new self('GET', '/classes/{classTemplate}/edit', $managers, true, $none),
            'update a class' => new self('PUT', '/classes/{classTemplate}', $managers, true, $classTemplate),
            'schedule' => new self('GET', '/schedule', $everyone, true, $none),
            'new session page' => new self('GET', '/schedule/create', $managers, true, $none),
            'schedule a session' => new self('POST', '/schedule', $managers, true, $classSession),
            'edit session page' => new self('GET', '/schedule/{classSession}/edit', $managers, true, $none),
            'update a session' => new self('PUT', '/schedule/{classSession}', $managers, true, $classSession),
            'book a session' => new self('POST', '/schedule/{classSession}/bookings', [Role::Customer], true, $none),
            'cancel a booking' => new self('DELETE', '/bookings/{booking}', [Role::Customer], true, $none,
                fn (StudioFixture $studio): array => ['{booking}' => $studio->customerBooking()->id]),
            'join a waitlist' => new self('POST', '/schedule/{classSession}/waitlist', [Role::Customer], true, $none,
                function (StudioFixture $studio): array {
                    $studio->fillSession();

                    return [];
                }),
            'billing page' => new self('GET', '/billing', [Role::Customer], true, $none),
            'buy a pass' => new self('POST', '/billing/checkout/{kind}', [Role::Customer], true, $none,
                function (StudioFixture $studio): array {
                    $studio->sellPasses();

                    return ['{kind}' => 'class_pack'];
                }),
            'return from checkout' => new self('GET', '/billing/checkouts/{checkout}/return', [Role::Customer], true, $none,
                fn (StudioFixture $studio): array => ['{checkout}' => $studio->customerPaidCheckout()->id]),
            'leave a waitlist' => new self('DELETE', '/waitlist/{waitlistEntry}', [Role::Customer], true, $none,
                fn (StudioFixture $studio): array => ['{waitlistEntry}' => $studio->customerWaitlistEntry()->id]),
        ];
    }

    /**
     * The actions as a Pest dataset: [name => [action]].
     *
     * @return array<string, array{self}>
     */
    public static function dataset(): array
    {
        return array_map(fn (self $action): array => [$action], self::all());
    }

    /**
     * Every action paired with every role, as a Pest dataset.
     *
     * @return array<string, array{self, Role}>
     */
    public static function withRoles(): array
    {
        $rows = [];

        foreach (self::all() as $name => $action) {
            foreach (Role::cases() as $role) {
                $rows["{$name} as {$role->value}"] = [$action, $role];
            }
        }

        return $rows;
    }

    /**
     * The action's URL on the studio, after creating the records it needs.
     */
    public function url(StudioFixture $studio): string
    {
        return $studio->url($this->path, $this->setup === null ? [] : ($this->setup)($studio));
    }

    public function allows(Role $role): bool
    {
        return in_array($role, $this->allowed, true);
    }
}
