<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. An active membership paid for the next month by
 * default.
 *
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
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
            'status' => MembershipStatus::Active,
            'current_period_ends_at' => now()->addMonth(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => MembershipStatus::Cancelled]);
    }
}
