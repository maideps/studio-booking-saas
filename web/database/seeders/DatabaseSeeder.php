<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: a demo studio with one member per
     * role, all with the factory password ("password"), two classes and a
     * week of sessions.
     *
     * Model events stay on: they fill tenant_id and guard tenant writes.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Studio',
            'slug' => 'demo',
            'timezone' => 'Europe/Madrid',
            'contact_email' => 'hello@demo-studio.test',
        ]);

        $tenant->run(function () use ($tenant): void {
            User::factory()->owner()->create(['name' => 'Olivia Owner', 'email' => 'owner@example.com']);
            $staff = User::factory()->staff()->create(['name' => 'Sam Staff', 'email' => 'staff@example.com']);
            User::factory()->customer()->create(['name' => 'Casey Customer', 'email' => 'customer@example.com']);

            $classes = [
                ['name' => 'Morning Flow', 'description' => 'A gentle start to the day.', 'duration_minutes' => 60, 'capacity' => 12, 'local_time' => '07:30'],
                ['name' => 'Evening Pilates', 'description' => 'Core strength after work.', 'duration_minutes' => 45, 'capacity' => 8, 'local_time' => '18:30'],
            ];

            foreach ($classes as $class) {
                $template = ClassTemplate::factory()->create(collect($class)->except('local_time')->all());

                foreach (range(1, 5) as $day) {
                    $startsAt = $tenant->parseLocalTime(now($tenant->timezone)->addDays($day)->format('Y-m-d').'T'.$class['local_time']);

                    ClassSession::factory()->create([
                        'class_template_id' => $template->id,
                        'instructor_id' => $staff->id,
                        'starts_at' => $startsAt,
                        'ends_at' => $startsAt->addMinutes($template->duration_minutes),
                        'capacity' => $template->capacity,
                    ]);
                }
            }
        });
    }
}
