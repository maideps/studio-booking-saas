<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Queue\RunInTenantContext;
use App\Tenancy\Queue\TenantAware;
use App\Tenancy\Queue\TenantAwareJob;
use App\Tenancy\TenancyViolation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Tenancy\FindUserJob;

use function Pest\Laravel\assertDatabaseCount;

it('hides tenant A records from a job dispatched in tenant B', function (): void {
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create());

    // dispatch() returns a pending dispatch that sends the job when it is
    // destroyed, after run() has already restored the empty context. So the
    // job only knows its tenant from the id it carries.
    $tenants->b->run(fn () => FindUserJob::dispatch($alice->id));

    expect(Cache::get(FindUserJob::RESULT_KEY))->toBe(['tenant' => 'studio-b', 'email' => null]);
});

it('runs a job on a worker inside the tenant that dispatched it', function (): void {
    config(['queue.default' => 'database']);
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create(['email' => 'alice@example.com']));

    $tenants->a->run(fn () => FindUserJob::dispatch($alice->id));

    assertDatabaseCount('jobs', 1);
    // The tenant travels in the serialized job, not in ambient state.
    expect(DB::table('jobs')->value('payload'))->toContain('tenantId')
        ->and(app(TenantContext::class)->current())->toBeNull();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);

    expect(Cache::get(FindUserJob::RESULT_KEY))->toBe(['tenant' => 'studio-a', 'email' => 'alice@example.com']);
    assertDatabaseCount('jobs', 0);
    assertDatabaseCount('failed_jobs', 0);
});

it('runs a job in its own tenant and restores the caller tenant afterwards', function (): void {
    $tenants = twoTenants();
    $alice = $tenants->a->run(fn () => User::factory()->create(['email' => 'alice@example.com']));
    $job = $tenants->a->run(fn () => new FindUserJob($alice->id));

    $tenants->b->run(function () use ($job, $tenants): void {
        Bus::dispatchSync($job);

        expect(app(TenantContext::class)->current()?->is($tenants->b))->toBeTrue();
    });

    expect(Cache::get(FindUserJob::RESULT_KEY))->toBe(['tenant' => 'studio-a', 'email' => 'alice@example.com']);
});

it('refuses to create a tenant-aware job outside a tenant', function (): void {
    expect(fn () => new FindUserJob(1))->toThrow(TenancyViolation::class);
});

it('fails a tenant-aware job that never captured its tenant', function (): void {
    $job = new class implements TenantAwareJob
    {
        use TenantAware;
    };

    expect(fn () => (new RunInTenantContext)->handle($job, fn () => null))
        ->toThrow(TenancyViolation::class, 'did not capture a tenant');
});

it('fails a job whose tenant no longer exists', function (): void {
    $tenant = Tenant::factory()->create();
    $job = $tenant->run(fn () => new FindUserJob(1));
    $tenant->delete();

    expect(fn () => (new RunInTenantContext)->handle($job, fn () => null))
        ->toThrow(TenancyViolation::class, "Tenant {$tenant->id} does not exist.");
});
