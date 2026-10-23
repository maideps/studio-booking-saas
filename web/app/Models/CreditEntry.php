<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditReason;
use App\Tenancy\BelongsToTenant;
use Database\Factories\CreditEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a customer's class-pack ledger (D-012). Entries are only ever
 * added; the balance is the sum of `change`.
 */
class CreditEntry extends Model
{
    /** @use HasFactory<CreditEntryFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

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
            'change' => 'integer',
            'reason' => CreditReason::class,
        ];
    }
}
