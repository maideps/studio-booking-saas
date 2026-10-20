<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\PasswordResetToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;

beforeEach(fn () => Notification::fake());

/**
 * The token in the last reset link sent to the user.
 */
function sentResetToken(User $user): string
{
    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    return (string) $token;
}

/**
 * @return array<int, string>
 */
function tokenEmails(Tenant $tenant): array
{
    return $tenant->run(fn () => PasswordResetToken::query()->orderBy('email')->get()
        ->map(fn (PasswordResetToken $token): string => $token->email)
        ->all());
}

/**
 * @return TestResponse<Response>
 */
function resetPassword(Tenant $tenant, string $email, string $token, string $password = 'a-new-password'): TestResponse
{
    return post(tenantUrl($tenant, '/reset-password'), [
        'token' => $token,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

function hasPassword(Tenant $tenant, int $userId, string $password): bool
{
    return $tenant->run(fn () => Hash::check($password, User::query()->findOrFail($userId)->password));
}

it('sends a reset link that points to the tenant subdomain', function (): void {
    $tenants = twoTenants();
    $alice = member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'alice@example.com'])->assertSessionHas('status');

    Notification::assertSentTo($alice, ResetPassword::class, function (ResetPassword $notification) use ($alice, $tenants): bool {
        $url = $notification->toMail($alice)->actionUrl;

        return parse_url($url, PHP_URL_HOST) === 'studio-a.localhost'
            && str_starts_with($url, $tenants->a->url("/reset-password/{$notification->token}?"));
    });
    expect(tokenEmails($tenants->a))->toBe(['alice@example.com'])
        ->and(tokenEmails($tenants->b))->toBe([]);
});

it('shows the reset form with the email from the link', function (): void {
    $tenants = twoTenants();

    get(tenantUrl($tenants->a, '/reset-password/some-token?email=alice@example.com'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'some-token')
            ->where('email', 'alice@example.com'));
});

it('answers the same way for an email that is not a member here', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    $status = 'If that email belongs to an account here, a password reset link is on its way.';

    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'alice@example.com'])->assertSessionHas('status', $status);
    post(tenantUrl($tenants->b, '/forgot-password'), ['email' => 'alice@example.com'])->assertSessionHas('status', $status);

    Notification::assertCount(1);
    expect(tokenEmails($tenants->b))->toBe([]);
});

it('gives the same error for an unknown email and a wrong token', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    resetPassword($tenants->a, 'alice@example.com', 'wrong-token')->assertSessionHasErrors(['email' => __('passwords.token')]);
    resetPassword($tenants->b, 'alice@example.com', 'wrong-token')->assertSessionHasErrors(['email' => __('passwords.token')]);
});

it('resets the password with a valid token', function (): void {
    $tenants = twoTenants();
    $alice = member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);
    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'alice@example.com']);

    resetPassword($tenants->a, 'alice@example.com', sentResetToken($alice))
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl($tenants->a, '/login'));

    expect(hasPassword($tenants->a, $alice->id, 'a-new-password'))->toBeTrue()
        ->and(tokenEmails($tenants->a))->toBe([]);
});

it('keeps the tokens of the same email in two tenants apart', function (): void {
    $tenants = twoTenants();
    $inA = member($tenants->a, Role::Customer, ['email' => 'sam@example.com']);
    $inB = member($tenants->b, Role::Customer, ['email' => 'sam@example.com']);

    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'sam@example.com']);
    $tokenA = sentResetToken($inA);
    post(tenantUrl($tenants->b, '/forgot-password'), ['email' => 'sam@example.com']);
    $tokenB = sentResetToken($inB);

    // Requesting a link in tenant B neither replaced nor throttled tenant A's token.
    expect($tokenA)->not->toBe($tokenB)
        ->and(tokenEmails($tenants->a))->toBe(['sam@example.com'])
        ->and(tokenEmails($tenants->b))->toBe(['sam@example.com']);

    // A tenant A token cannot reset the tenant B account.
    resetPassword($tenants->b, 'sam@example.com', $tokenA)->assertSessionHasErrors('email');
    expect(hasPassword($tenants->b, $inB->id, 'password'))->toBeTrue()
        ->and(hasPassword($tenants->a, $inA->id, 'password'))->toBeTrue();

    // Each token resets its own tenant's account only.
    resetPassword($tenants->a, 'sam@example.com', $tokenA, 'new-password-for-a')->assertSessionHasNoErrors();
    expect(hasPassword($tenants->a, $inA->id, 'new-password-for-a'))->toBeTrue()
        ->and(hasPassword($tenants->b, $inB->id, 'password'))->toBeTrue()
        ->and(tokenEmails($tenants->b))->toBe(['sam@example.com']);
});

it('throttles new links per tenant', function (): void {
    $tenants = twoTenants();
    $inA = member($tenants->a, Role::Customer, ['email' => 'sam@example.com']);
    $inB = member($tenants->b, Role::Customer, ['email' => 'sam@example.com']);

    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'sam@example.com']);
    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'sam@example.com']);
    post(tenantUrl($tenants->b, '/forgot-password'), ['email' => 'sam@example.com']);

    Notification::assertSentToTimes($inA, ResetPassword::class, 1);
    Notification::assertSentToTimes($inB, ResetPassword::class, 1);
});

it('rejects an expired token', function (): void {
    $tenants = twoTenants();
    $alice = member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);
    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'alice@example.com']);

    travel(config()->integer('auth.passwords.users.expire') + 1)->minutes();

    resetPassword($tenants->a, 'alice@example.com', sentResetToken($alice))->assertSessionHasErrors('email');
    expect(hasPassword($tenants->a, $alice->id, 'password'))->toBeTrue();
});

it('purges expired tokens of every tenant', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Customer, ['email' => 'old@example.com']);
    member($tenants->b, Role::Customer, ['email' => 'old@example.com']);
    member($tenants->b, Role::Customer, ['email' => 'fresh@example.com']);

    post(tenantUrl($tenants->a, '/forgot-password'), ['email' => 'old@example.com']);
    post(tenantUrl($tenants->b, '/forgot-password'), ['email' => 'old@example.com']);
    travel(config()->integer('auth.passwords.users.expire') + 1)->minutes();
    post(tenantUrl($tenants->b, '/forgot-password'), ['email' => 'fresh@example.com']);

    // A console command runs without a tenant.
    expect(Artisan::call('auth:clear-resets'))->toBe(0)
        ->and(tokenEmails($tenants->a))->toBe([])
        ->and(tokenEmails($tenants->b))->toBe(['fresh@example.com']);
});
