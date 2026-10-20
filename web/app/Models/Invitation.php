<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending invitation to join a studio as staff or owner. Only a SHA-256
 * hash of the token is stored; the plain token is only in the emailed link.
 * Accepting the invitation deletes it.
 */
#[Fillable(['email', 'role', 'token', 'expires_at'])]
#[Hidden(['token'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    /** Days an invitation link stays valid. */
    public const int VALID_DAYS = 7;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The pending invitation for a plain token, in the current tenant.
     */
    public static function findPending(string $token): ?self
    {
        return self::query()
            ->where('token', self::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
        ];
    }
}
