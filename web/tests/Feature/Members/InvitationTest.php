<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(fn () => Notification::fake());

/**
 * The plain token in the last invitation emailed to the address.
 */
function sentInvitationToken(string $email): string
{
    $token = null;

    Notification::assertSentTo(new AnonymousNotifiable, StaffInvitation::class, function (StaffInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token): bool {
        if ($notifiable->routeNotificationFor('mail') !== $email) {
            return false;
        }

        $token = $notification->token;

        return true;
    });

    return (string) $token;
}

/**
 * @return array<int, string>
 */
function pendingInvitations(Tenant $tenant): array
{
    return $tenant->run(fn () => Invitation::query()->orderBy('email')->get()
        ->map(fn (Invitation $invitation): string => $invitation->email.' as '.$invitation->role->value)
        ->values()->all());
}

/**
 * @return array<string, string>
 */
function newAccount(): array
{
    return ['name' => 'Nina New', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'];
}

it('emails an invitation link on the studio subdomain and stores only a hash of the token', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))
        ->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'nina@example.com', 'role' => 'staff'])
        ->assertSessionHasNoErrors();

    $token = sentInvitationToken('nina@example.com');
    $invitation = $tenants->a->run(fn () => Invitation::query()->sole());

    Notification::assertSentTo(new AnonymousNotifiable, StaffInvitation::class, fn (StaffInvitation $notification): bool => $notification->url() === $tenants->a->url("/join/{$token}"));
    expect($invitation->token)->toBe(Invitation::hashToken($token))
        ->and($invitation->token === $token)->toBeFalse()
        ->and(pendingInvitations($tenants->a))->toBe(['nina@example.com as staff'])
        ->and(pendingInvitations($tenants->b))->toBe([]);
});

it('replaces a pending invitation when the same email is invited again', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);

    actingAs($owner)->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'nina@example.com', 'role' => 'staff']);
    $first = sentInvitationToken('nina@example.com');
    Notification::fake();
    actingAs($owner)->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'nina@example.com', 'role' => 'owner']);

    expect(pendingInvitations($tenants->a))->toBe(['nina@example.com as owner']);
    get(tenantUrl($tenants->a, "/join/{$first}"))->assertNotFound();
});

it('refuses to invite someone who is already on the staff', function (Role $role): void {
    $tenants = twoTenants();
    member($tenants->a, $role, ['email' => 'sam@example.com']);

    actingAs(member($tenants->a, Role::Owner))
        ->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'sam@example.com', 'role' => 'staff'])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
})->with(['owner' => Role::Owner, 'staff' => Role::Staff]);

it('invites the staff of another studio and customers of this one', function (): void {
    $tenants = twoTenants();
    member($tenants->b, Role::Staff, ['email' => 'sam@example.com']);
    member($tenants->a, Role::Customer, ['email' => 'casey@example.com']);
    $owner = member($tenants->a, Role::Owner);

    actingAs($owner)->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'sam@example.com', 'role' => 'staff'])->assertSessionHasNoErrors();
    actingAs($owner)->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'casey@example.com', 'role' => 'staff'])->assertSessionHasNoErrors();

    expect(pendingInvitations($tenants->a))->toBe(['casey@example.com as staff', 'sam@example.com as staff']);
});

it('only invites as staff or owner', function (): void {
    $tenants = twoTenants();

    actingAs(member($tenants->a, Role::Owner))
        ->post(tenantUrl($tenants->a, '/invitations'), ['email' => 'nina@example.com', 'role' => 'customer'])
        ->assertSessionHasErrors('role');
});

it('creates the account with the invited role when the invitee is new here', function (): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => Invitation::factory()->withToken('the-token')->create(['email' => 'nina@example.com', 'role' => Role::Staff]));

    get(tenantUrl($tenants->a, '/join/the-token'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Join')
            ->where('email', 'nina@example.com')
            ->where('role', 'Staff')
            ->where('hasAccount', false)
            ->where('signedInAs', null));

    post(tenantUrl($tenants->a, '/join/the-token'), newAccount())->assertRedirect(tenantUrl($tenants->a, '/dashboard'));

    $nina = $tenants->a->run(fn () => User::query()->where('email', 'nina@example.com')->sole());

    expect($nina->role)->toBe(Role::Staff)
        ->and($nina->tenant_id)->toBe($tenants->a->id)
        ->and(pendingInvitations($tenants->a))->toBe([]);
    $tenants->a->run(fn () => assertAuthenticatedAs($nina));
});

it('asks an existing member to sign in, then changes their role', function (): void {
    $tenants = twoTenants();
    $casey = member($tenants->a, Role::Customer, ['email' => 'casey@example.com']);
    $tenants->a->run(fn () => Invitation::factory()->withToken('the-token')->create(['email' => 'casey@example.com', 'role' => Role::Staff]));

    // As a guest: no account is created, and the login page comes first.
    post(tenantUrl($tenants->a, '/join/the-token'), newAccount())->assertRedirect(tenantUrl($tenants->a, '/login'));
    expect(roleOf($casey))->toBe(Role::Customer)
        ->and($tenants->a->run(fn () => User::query()->where('email', 'casey@example.com')->count()))->toBe(1);

    actingAs($casey)->post(tenantUrl($tenants->a, '/join/the-token'))->assertRedirect(tenantUrl($tenants->a, '/dashboard'));

    expect(roleOf($casey))->toBe(Role::Staff)
        ->and(pendingInvitations($tenants->a))->toBe([]);
});

it('brings an existing member back to the invitation after they log in', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'casey@example.com']);
    $tenants->a->run(fn () => Invitation::factory()->withToken('the-token')->create(['email' => 'casey@example.com']));

    get(tenantUrl($tenants->a, '/join/the-token'))->assertInertia(fn (Assert $page) => $page->where('hasAccount', true));

    signIn($tenants->a, 'casey@example.com')->assertRedirect(tenantUrl($tenants->a, '/join/the-token'));
});

it('never demotes an owner who accepts a staff invitation', function (): void {
    $tenants = twoTenants();
    $olivia = member($tenants->a, Role::Owner, ['email' => 'olivia@example.com']);
    $tenants->a->run(fn () => Invitation::factory()->withToken('the-token')->create(['email' => 'olivia@example.com', 'role' => Role::Staff]));

    actingAs($olivia)->post(tenantUrl($tenants->a, '/join/the-token'))->assertRedirect(tenantUrl($tenants->a, '/dashboard'));

    expect(roleOf($olivia))->toBe(Role::Owner)
        ->and(pendingInvitations($tenants->a))->toBe([]);
});

it('does not let another signed-in member accept', function (): void {
    $tenants = twoTenants();
    $other = member($tenants->a, Role::Customer, ['email' => 'other@example.com']);
    $tenants->a->run(fn () => Invitation::factory()->withToken('the-token')->create(['email' => 'nina@example.com']));

    actingAs($other)->post(tenantUrl($tenants->a, '/join/the-token'), newAccount())->assertForbidden();

    expect(roleOf($other))->toBe(Role::Customer)
        ->and(pendingInvitations($tenants->a))->toBe(['nina@example.com as staff']);
});

it('refuses a wrong, expired or other-studio token', function (string $case): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => Invitation::factory()->withToken('studio-a-token')->create(['email' => 'nina@example.com']));
    $tenants->a->run(fn () => Invitation::factory()->withToken('expired-token')->expired()->create(['email' => 'old@example.com']));

    [$tenant, $token] = match ($case) {
        'wrong' => [$tenants->a, 'not-a-token'],
        'expired' => [$tenants->a, 'expired-token'],
        'other studio' => [$tenants->b, 'studio-a-token'],
        default => throw new LogicException("Unknown case {$case}."),
    };

    get(tenantUrl($tenant, "/join/{$token}"))->assertNotFound();
    post(tenantUrl($tenant, "/join/{$token}"), newAccount())->assertNotFound();

    expect($tenants->b->run(fn () => User::query()->count()))->toBe(0)
        ->and($tenants->a->run(fn () => User::query()->count()))->toBe(0);
})->with(['wrong', 'expired', 'other studio']);

it('revokes a pending invitation, but not one of another studio', function (): void {
    $tenants = twoTenants();
    $owner = member($tenants->a, Role::Owner);
    $ours = $tenants->a->run(fn () => Invitation::factory()->create(['email' => 'nina@example.com']));
    $theirs = $tenants->b->run(fn () => Invitation::factory()->create(['email' => 'bert@example.com']));

    actingAs($owner)->delete(tenantUrl($tenants->a, "/invitations/{$theirs->id}"))->assertNotFound();
    actingAs($owner)->delete(tenantUrl($tenants->a, "/invitations/{$ours->id}"))->assertRedirect(tenantUrl($tenants->a, '/members'));

    expect(pendingInvitations($tenants->a))->toBe([])
        ->and(pendingInvitations($tenants->b))->toBe(['bert@example.com as staff']);
});

it('lists pending invitations to owners only', function (Role $role, int $count): void {
    $tenants = twoTenants();
    $tenants->a->run(fn () => Invitation::factory()->create(['email' => 'nina@example.com']));
    $tenants->a->run(fn () => Invitation::factory()->expired()->create(['email' => 'old@example.com']));
    $tenants->b->run(fn () => Invitation::factory()->create(['email' => 'bert@example.com']));

    actingAs(member($tenants->a, $role))->get(tenantUrl($tenants->a, '/members'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canInvite', $role === Role::Owner)
            ->has('invitations', $count));
})->with([
    'owner' => [Role::Owner, 1],
    'staff' => [Role::Staff, 0],
]);
