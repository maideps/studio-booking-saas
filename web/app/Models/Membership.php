<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's monthly membership at one studio (D-012). One row per
 * customer: buying again after a cancellation updates it.
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Whether it covers bookings now: active, and its paid period not over.
     */
    public function coversBookings(): bool
    {
        return $this->status === MembershipStatus::Active
            && $this->current_period_ends_at !== null
            && $this->current_period_ends_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'current_period_ends_at' => 'immutable_datetime',
        ];
    }
}
