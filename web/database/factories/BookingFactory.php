<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. The session and the customer are created in the
 * same tenant unless given.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
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
     * A booking that no longer holds a place.
     */
    public function released(): static
    {
        return $this->state(['active' => null]);
    }
}
