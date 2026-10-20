<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

/**
 * Gives every password broker the tenant-aware token repository (D-008).
 * Laravel's manager has no hook for a custom repository, so this overrides
 * the method that builds it; the `table`, `connection` and `driver` broker
 * options no longer apply.
 */
final class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  array{expire?: int, throttle?: int}  $config
     */
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        $key = config()->string('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        return new TenantTokenRepository(
            $this->app->make('hash'),
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
