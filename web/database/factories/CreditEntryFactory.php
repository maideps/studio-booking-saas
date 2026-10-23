<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditReason;
use App\Models\CreditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. A purchase of 10 credits without a checkout by
 * default, for setting up a balance in tests.
 *
 * @extends Factory<CreditEntry>
 */
class CreditEntryFactory extends Factory
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
            'change' => 10,
            'reason' => CreditReason::Purchase,
        ];
    }
}
