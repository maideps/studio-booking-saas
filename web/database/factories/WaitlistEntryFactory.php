<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. The session and the customer are created in the
 * same tenant unless given.
 *
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_session_id' => ClassSession::factory(),
            'customer_id' => User::factory()->customer(),
            'active' => true,
        ];
    }

    /**
     * An entry that no longer waits.
     */
    public function released(): static
    {
        return $this->state(['active' => null]);
    }
}
