<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant: `$tenant->run(fn () => ClassTemplate::factory()->create())`.
 *
 * @extends Factory<ClassTemplate>
 */
class ClassTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('Class ####'),
            'description' => fake()->sentence(),
            'duration_minutes' => 60,
            'capacity' => 12,
        ];
    }
}
