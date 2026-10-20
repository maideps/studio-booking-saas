<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Route;
use Tests\Support\ProtectedActions;
use Tests\Support\StudioFixture;

use function Pest\Laravel\actingAs;

// Every protected action for every role, and for an owner of another
// studio. Guests and replayed sessions of another studio are covered for
// every action in LoginTest and SessionIsolationTest.

it('lets each role do exactly what its policy allows', function (ProtectedActions $action, Role $role): void {
    $studio = StudioFixture::create(twoTenants()->a);

    $response = actingAs($studio->user($role))
        ->call($action->method, $action->url($studio), ($action->payload)($studio));

    if ($action->allows($role)) {
        expect($response->status())->toBeLessThan(400);
        $response->assertSessionHasNoErrors();
    } else {
        $response->assertForbidden();
    }
})->with('protected actions by role');

it('refuses every authorized action to an owner of another studio', function (ProtectedActions $action): void {
    $tenants = twoTenants();
    $studioB = StudioFixture::create($tenants->b);
    // Signed in as studio A's owner while the request runs in studio B. The
    // guard never returns this user on studio B (SessionIsolationTest); this
    // checks that the policies refuse it even if it did.
    $foreignOwner = member($tenants->a, Role::Owner);

    actingAs($foreignOwner)
        ->call($action->method, $action->url($studioB), ($action->payload)($studioB))
        ->assertForbidden();
})->with(fn (): array => array_filter(ProtectedActions::dataset(), fn (array $row): bool => $row[0]->authorized));

it('covers every route that needs a signed-in user', function (): void {
    app(Kernel::class);
    $router = app('router');

    $protected = collect($router->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => collect($router->gatherRouteMiddleware($route))
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, Authenticate::class)))
        ->map(fn (Route $route): string => collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->implode('|').' /'.$route->uri())
        ->sort()->values()->all();

    $tested = collect(ProtectedActions::all())
        ->map(fn (ProtectedActions $action): string => $action->method.' '.$action->path)
        ->sort()->values()->all();

    expect($protected)->toBe($tested);
});
