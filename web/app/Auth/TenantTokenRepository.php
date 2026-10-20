<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\PasswordResetToken;
use App\Tenancy\CrossTenant;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Password reset tokens per tenant (D-008).
 *
 * Replaces Laravel's DatabaseTokenRepository, which keys tokens by email
 * alone. Tokens are PasswordResetToken models, so every read and write goes
 * through the tenant scope and write guards: a reset requested in one
 * tenant never replaces, matches or throttles the token of the same email
 * in another tenant.
 */
final readonly class TenantTokenRepository implements TokenRepositoryInterface
{
    /**
     * @param  int  $expires  seconds a token stays valid
     * @param  int  $throttle  seconds before the same user may request a new token
     */
    public function __construct(
        private Hasher $hasher,
        #[SensitiveParameter] private string $hashKey,
        private int $expires = 3600,
        private int $throttle = 60,
    ) {}

    public function create(CanResetPassword $user): string
    {
        $this->delete($user);

        $token = hash_hmac('sha256', Str::random(40), $this->hashKey);

        PasswordResetToken::query()->create([
            'email' => $user->getEmailForPasswordReset(),
            'token' => $this->hasher->make($token),
        ]);

        return $token;
    }

    /**
     * @param  string  $token
     */
    public function exists(CanResetPassword $user, #[SensitiveParameter] $token): bool
    {
        $record = $this->recordFor($user);

        return $record !== null
            && ! $this->createdBefore($record, $this->expires)
            && $this->hasher->check($token, $record->token);
    }

    public function recentlyCreatedToken(CanResetPassword $user): bool
    {
        $record = $this->recordFor($user);

        return $this->throttle > 0 && $record !== null && ! $this->createdBefore($record, $this->throttle);
    }

    public function delete(CanResetPassword $user): void
    {
        PasswordResetToken::query()->where('email', $user->getEmailForPasswordReset())->delete();
    }

    /**
     * Platform maintenance (`php artisan auth:clear-resets`): runs without a
     * tenant and purges the expired tokens of every tenant.
     */
    public function deleteExpired(): void
    {
        CrossTenant::query(PasswordResetToken::class)
            ->where('created_at', '<', Carbon::now()->subSeconds($this->expires))
            ->delete();
    }

    private function recordFor(CanResetPassword $user): ?PasswordResetToken
    {
        return PasswordResetToken::query()->where('email', $user->getEmailForPasswordReset())->first();
    }

    private function createdBefore(PasswordResetToken $record, int $seconds): bool
    {
        return $record->created_at === null || $record->created_at->copy()->addSeconds($seconds)->isPast();
    }
}
