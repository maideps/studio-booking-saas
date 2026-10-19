<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenantFromSubdomain;
use App\Models\Tenant;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\Queue\TenantAware;
use App\Tenancy\Queue\TenantAwareJob;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TenancyArchitecture;

it('uses BelongsToTenant on every tenant-owned model', function (): void {
    expect(TenancyArchitecture::appModels())->toContain(Tenant::class)
        ->and(TenancyArchitecture::modelsMissingTenantTrait(TenancyArchitecture::appModels(), TenancyArchitecture::CENTRAL_MODELS))
        ->toBe([]);
});

it('reports a model that lacks BelongsToTenant', function (): void {
    $unscoped = new class extends Model {};
    $scoped = new class extends Model
    {
        use BelongsToTenant;
    };

    expect(TenancyArchitecture::modelsMissingTenantTrait(
        [$unscoped::class, $scoped::class, Tenant::class],
        TenancyArchitecture::CENTRAL_MODELS,
    ))->toBe([$unscoped::class]);
});

it('gives every tenant-owned table an indexed tenant_id that references tenants', function (): void {
    $tenantOwned = array_diff(TenancyArchitecture::appModels(), TenancyArchitecture::CENTRAL_MODELS);

    expect($tenantOwned)->not->toBeEmpty();

    foreach ($tenantOwned as $model) {
        expect(TenancyArchitecture::tenantColumnProblems((new $model)->getTable()))->toBe([]);
    }
});

it('reports a tenant table without an indexed, constrained tenant_id', function (): void {
    Schema::create('no_tenant_column', fn (Blueprint $table) => $table->id());
    Schema::create('loose_tenant_column', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('tenant_id');
    });

    expect(TenancyArchitecture::tenantColumnProblems('no_tenant_column'))
        ->toBe(['no_tenant_column has no tenant_id'])
        ->and(TenancyArchitecture::tenantColumnProblems('loose_tenant_column'))
        ->toBe([
            'loose_tenant_column.tenant_id has no index',
            'loose_tenant_column.tenant_id does not reference tenants',
        ]);
});

it('makes every queued job tenant-aware', function (): void {
    expect(TenancyArchitecture::queuedJobsWithoutTenant(TenancyArchitecture::appClasses(), TenancyArchitecture::CENTRAL_JOBS))
        ->toBe([]);
});

it('reports a queued job that is not tenant-aware', function (): void {
    $plain = new class implements ShouldQueue {};
    $aware = new class implements ShouldQueue, TenantAwareJob
    {
        use TenantAware;
    };

    expect(TenancyArchitecture::queuedJobsWithoutTenant([$plain::class, $aware::class, Tenant::class], []))
        ->toBe([$plain::class]);
});

it('bypasses the tenant scope only through CrossTenant', function (): void {
    $bypasses = TenancyArchitecture::scopeBypassesInDirectories(
        [app_path(), base_path('routes'), database_path()],
        allowedFiles: [app_path('Tenancy/CrossTenant.php')],
    );

    expect($bypasses)->toBe([]);
});

it('reports scope bypasses in source code', function (): void {
    $source = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\DB as Database;
        use Illuminate\Database\DatabaseManager;
        User::withoutGlobalScopes()->get();
        $query->withoutGlobalScope(TenantScope::class);
        $user?->saveQuietly();
        User::forceCreateQuietly(['tenant_id' => 2]);
        DB::table('users')->get();
        \Illuminate\Support\Facades\DB::select('select * from users');
        Database::table('users')->get();
        User::query()->insert([]);
        User::query()->getQuery()->count();
        $user->getConnection()->select('select 1');
        app('db')->table('users');
        $query->{'withoutGlobalScopes'}();
        $query->$method();
        class Seeder { use WithoutModelEvents; }
        PHP;

    expect(TenancyArchitecture::scopeBypassesIn($source))->toBe([
        'line 3: DatabaseManager',
        'line 4: ::withoutGlobalScopes',
        'line 5: ->withoutGlobalScope',
        'line 6: ?->saveQuietly',
        'line 7: ::forceCreateQuietly',
        'line 8: DB::table',
        'line 9: DB::select',
        'line 10: DB::table',
        'line 11: ->insert',
        'line 12: ->getQuery',
        'line 13: ->getConnection',
        'line 14: container \'db\'',
        'line 15: dynamic call ->{',
        'line 16: dynamic call ->$method',
        'line 17: WithoutModelEvents',
    ]);
});

it('does not report safe code that only mentions bypass names', function (): void {
    $source = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\DB;
        // User::withoutGlobalScopes() in a comment
        $label = 'withoutGlobalScopes';
        function insert(): void {}
        DB::transaction(fn () => User::query()->where('active', true)->get());
        $value = $this->$property;
        config('database.default');
        PHP;

    expect(TenancyArchitecture::scopeBypassesIn($source))->toBe([]);
});

it('resolves the tenant before the session, authentication and route model binding', function (): void {
    // The HTTP kernel hands its middleware aliases and priority to the router.
    app(Kernel::class);
    $route = Route::middleware(['web', 'auth', 'tenant'])->get('/_tenancy/order', fn () => 'ok');

    $order = array_values(app('router')->gatherRouteMiddleware($route));

    expect($order[0])->toBe(ResolveTenantFromSubdomain::class)
        ->and($order)->toContain(StartSession::class, Authenticate::class, SubstituteBindings::class);
});
