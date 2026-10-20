<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\TenantPasswordBrokerManager;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: each request and each queued job starts without a tenant.
        $this->app->scoped(TenantContext::class);

        // Password reset tokens are stored per tenant (D-008).
        $this->app->extend('auth.password', fn (mixed $manager, Application $app) => new TenantPasswordBrokerManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Before any policy runs: a user may only act inside their own tenant,
        // and nobody may act when there is no tenant (D-008). Returning null
        // hands the decision to the policy.
        Gate::before(fn (User $user): ?bool => $user->belongsToCurrentTenant() ? null : false);

        // The reset link goes to the user's own studio, built from the user's
        // tenant rather than the current request.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => $user->tenant()->firstOrFail()->url(
            route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], absolute: false),
        ));
    }
}
