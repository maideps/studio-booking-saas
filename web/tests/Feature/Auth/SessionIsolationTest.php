<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\ProtectedActions;
use Tests\Support\StudioFixture;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\withCookies;

// The test suite uses the database session driver (phpunit.xml), as the app
// does by default. Saving a session writes the signed-in user's id, so the
// session save must run inside the tenant (D-007).

/**
 * The cookies a browser keeps after the response, decrypted.
 *
 * @param  TestResponse<Response>  $response
 * @return array<string, string>
 */
function browserCookies(TestResponse $response): array
{
    $cookies = [];

    foreach ($response->headers->getCookies() as $cookie) {
        $value = $response->getCookie($cookie->getName())?->getValue();

        if (is_string($value)) {
            $cookies[$cookie->getName()] = $value;
        }
    }

    return $cookies;
}

it('stores the session of a signed-in user in the database', function (): void {
    $tenants = twoTenants();
    $alice = member($tenants->a, Role::Customer, ['email' => 'alice@example.com']);

    signIn($tenants->a, 'alice@example.com')->assertRedirect('/dashboard');

    expect(config('session.driver'))->toBe('database');
    assertDatabaseHas('sessions', ['user_id' => $alice->id]);
});

it('serves the home page to a signed-in member on their tenant subdomain', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Owner, ['email' => 'alice@example.com']);
    $cookies = browserCookies(signIn($tenants->a, 'alice@example.com'));

    // The home page is outside the tenant group. With a signed-in session it
    // must still render, not fail closed while sharing props or saving the session.
    startNewRequest();
    withCookies($cookies)->get(tenantUrl($tenants->a, '/'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome')->where('auth.user', null));
});

it('keeps a tenant A session signed in on tenant A only', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Owner, ['email' => 'alice@example.com']);
    $cookies = browserCookies(signIn($tenants->a, 'alice@example.com'));

    // Without the cookie a new request is a guest: the replays below prove
    // something only because the cookie is what signs them in.
    startNewRequest();
    get(tenantUrl($tenants->a, '/dashboard'))->assertRedirect(tenantUrl($tenants->a, '/login'));

    startNewRequest();
    withCookies($cookies)->get(tenantUrl($tenants->a, '/dashboard'))->assertOk();

    startNewRequest();
    withCookies($cookies)->get(tenantUrl($tenants->b, '/dashboard'))->assertRedirect(tenantUrl($tenants->b, '/login'));
    $tenants->b->run(fn () => assertGuest());
});

it('does not sign in on tenant B with a tenant A remember-me cookie', function (): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Owner, ['email' => 'alice@example.com']);
    $cookies = browserCookies(signIn($tenants->a, 'alice@example.com', remember: true));
    $recaller = array_filter($cookies, fn (string $name): bool => str_starts_with($name, 'remember_web_'), ARRAY_FILTER_USE_KEY);

    expect($recaller)->toHaveCount(1);

    // The remember-me cookie alone, without the session, signs in on tenant A...
    startNewRequest();
    withCookies($recaller)->get(tenantUrl($tenants->a, '/dashboard'))->assertOk();

    // ...but not on tenant B.
    startNewRequest();
    withCookies($recaller)->get(tenantUrl($tenants->b, '/dashboard'))->assertRedirect(tenantUrl($tenants->b, '/login'));
    $tenants->b->run(fn () => assertGuest());
});

it('denies every protected action on tenant B to a tenant A session', function (ProtectedActions $action): void {
    $tenants = twoTenants();
    member($tenants->a, Role::Owner, ['email' => 'alice@example.com']);
    $studioB = StudioFixture::create($tenants->b);
    $cookies = browserCookies(signIn($tenants->a, 'alice@example.com'));

    startNewRequest();
    withCookies($cookies)
        ->call($action->method, $action->url($studioB), ($action->payload)($studioB))
        ->assertRedirect(tenantUrl($tenants->b, '/login'));

    expect($tenants->b->run(fn () => User::query()->findOrFail($studioB->member->id)->role))->toBe(Role::Customer)
        ->and($tenants->b->fresh()?->name)->toBe('Studio B');

    // The visit to tenant B did not sign Alice out of tenant A.
    startNewRequest();
    withCookies($cookies)->get(tenantUrl($tenants->a, '/dashboard'))->assertOk();
})->with('protected actions');
