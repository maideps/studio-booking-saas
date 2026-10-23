<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\TenantPasswordBrokerManager;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use App\Billing\StripeGateway;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Stripe\StripeClient;

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

        // Stripe in test mode, or the fake for tests and the local demo (D-004, D-012).
        $this->app->singleton(PaymentGateway::class, function (Application $app): PaymentGateway {
            if (config('billing.gateway') === 'stripe') {
                return new StripeGateway(new StripeClient(config()->string('billing.stripe.secret')));
            }

            if (! $app->environment(['local', 'testing'])) {
                throw new RuntimeException('The fake payment gateway is only allowed in the local and testing environments. Set STRIPE_SECRET.');
            }

            return new FakePaymentGateway(autoPay: ! $app->environment('testing'));
        });
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
