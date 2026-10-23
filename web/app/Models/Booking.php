<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingPayment;
use App\Tenancy\BelongsToTenant;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's place in a class session. Only BookSession creates bookings.
 *
 * `active` is true while the booking holds a place and NULL once released,
 * never false: the one-active-booking unique index relies on NULLs being
 * distinct (D-010).
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * Bookings that hold a place.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
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
            'active' => 'boolean',
            'paid_with' => BookingPayment::class,
        ];
    }
}
