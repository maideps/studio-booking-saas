<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signs a user in to the current tenant. The user provider queries through
 * the tenant scope, so only a user of this tenant can match the credentials.
 */
final class LoginRequest extends FormRequest
{
    /** Failed attempts allowed per tenant, email and IP before a lockout. */
    public const int MAX_ATTEMPTS = 5;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
                'minutes' => (int) ceil(RateLimiter::availableIn($this->throttleKey()) / 60),
            ]),
        ]);
    }

    /**
     * Cache keys are not tenant-prefixed (D-007), so the key carries the
     * tenant id: a lockout in one tenant never locks the same email out of
     * another.
     */
    private function throttleKey(): string
    {
        return sprintf(
            'login:%d:%s|%s',
            app(TenantContext::class)->currentOrFail()->id,
            Str::transliterate(Str::lower($this->string('email')->toString())),
            $this->ip(),
        );
    }
}
