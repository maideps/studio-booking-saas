<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Tenant;

/**
 * Two tenants for isolation tests: data created in one must never be
 * visible from the other.
 */
final readonly class TwoTenants
{
    public function __construct(
        public Tenant $a,
        public Tenant $b,
    ) {}

    public static function create(): self
    {
        return new self(
            Tenant::factory()->create(['name' => 'Studio A', 'slug' => 'studio-a']),
            Tenant::factory()->create(['name' => 'Studio B', 'slug' => 'studio-b']),
        );
    }
}
