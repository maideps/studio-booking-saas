<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer waiting for a place in a full session. Entries are served in id
 * order. `active` is true while waiting and NULL once the customer leaves or
 * is promoted, never false (D-010, D-011).
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * Entries still waiting, first in line first.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWaiting(Builder $query): void
    {
        $query->where('active', true)->orderBy('id');
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
        ];
    }
}
