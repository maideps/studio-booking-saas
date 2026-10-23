<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Billing\CheckoutKind;
use App\Enums\CheckoutStatus;
use App\Models\Checkout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. A pending class-pack checkout of 10 credits by
 * default.
 *
 * @extends Factory<Checkout>
 */
class CheckoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'kind' => CheckoutKind::ClassPack,
            'amount_cents' => 9000,
            'currency' => 'eur',
            'credits' => 10,
            'status' => CheckoutStatus::Pending,
        ];
    }

    public function membership(): static
    {
        return $this->state(['kind' => CheckoutKind::Membership, 'amount_cents' => 4900, 'credits' => null]);
    }
}
