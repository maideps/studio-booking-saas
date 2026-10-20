<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Create inside a tenant. Pass a known plain token with withToken() when the
 * test needs to follow the link.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Staff,
            'token' => Invitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ];
    }

    public function withToken(string $token): static
    {
        return $this->state(['token' => Invitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }
}
