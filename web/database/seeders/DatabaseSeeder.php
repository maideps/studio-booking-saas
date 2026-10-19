<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay on: they fill tenant_id and guard tenant writes.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Studio',
            'slug' => 'demo',
        ]);

        $tenant->run(fn () => User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]));
    }
}
