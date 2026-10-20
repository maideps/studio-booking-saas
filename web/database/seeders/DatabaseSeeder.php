<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: a demo studio with one member per
     * role, all with the factory password ("password").
     *
     * Model events stay on: they fill tenant_id and guard tenant writes.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Studio',
            'slug' => 'demo',
        ]);

        $tenant->run(function (): void {
            User::factory()->owner()->create(['name' => 'Olivia Owner', 'email' => 'owner@example.com']);
            User::factory()->staff()->create(['name' => 'Sam Staff', 'email' => 'staff@example.com']);
            User::factory()->customer()->create(['name' => 'Casey Customer', 'email' => 'customer@example.com']);
        });
    }
}
