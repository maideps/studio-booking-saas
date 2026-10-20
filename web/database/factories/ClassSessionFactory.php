<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant. The template and the instructor are created in the
 * same tenant unless given.
 *
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeek()->startOfHour();

        return [
            'class_template_id' => ClassTemplate::factory(),
            'instructor_id' => User::factory()->staff(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 12,
        ];
    }
}
