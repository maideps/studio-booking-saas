<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\CheckoutKind;
use App\Enums\CheckoutStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\CheckoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One purchase attempt at the payment gateway: a membership or a class pack.
 * Created by StartCheckout, completed (once) by FulfilCheckout (D-012).
 */
class Checkout extends Model
{
    /** @use HasFactory<CheckoutFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

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
            'kind' => CheckoutKind::class,
            'status' => CheckoutStatus::class,
            'amount_cents' => 'integer',
            'credits' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
