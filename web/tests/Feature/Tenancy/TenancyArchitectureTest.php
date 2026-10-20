<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenantFromSubdomain;
use App\Models\Tenant;
use App\Models\User;
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

it('references other tenant-owned tables only through tenant_id', function (): void {
    $tables = array_values(array_map(
        fn (string $model): string => (new $model)->getTable(),
        array_diff(TenancyArchitecture::appModels(), TenancyArchitecture::CENTRAL_MODELS),
    ));

    expect($tables)->toContain('class_sessions', 'invitations');

    foreach ($tables as $table) {
        expect(TenancyArchitecture::crossTenantForeignKeys($table, $tables))->toBe([]);
    }
});

it('reports a reference to a tenant-owned table without tenant_id', function (): void {
    Schema::create('arch_parents', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained();
        $table->unique(['tenant_id', 'id']);
    });
    Schema::create('arch_children', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained();
        $table->foreignId('loose_parent_id')->constrained('arch_parents');
        $table->unsignedBigInteger('scoped_parent_id');
        $table->foreign(['tenant_id', 'scoped_parent_id'])->references(['tenant_id', 'id'])->on('arch_parents');
    });

    expect(TenancyArchitecture::crossTenantForeignKeys('arch_children', ['arch_parents', 'arch_children']))
        ->toBe(['arch_children.loose_parent_id references arch_parents without tenant_id']);
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

it('checks unique and exists rules only through TenantRule', function (): void {
    $rules = TenancyArchitecture::untenantedValidationRulesInDirectories(
        [app_path(), base_path('routes'), database_path()],
        allowedFiles: [app_path('Tenancy/TenantRule.php')],
    );

    expect($rules)->toBe([]);
});

it('reports unique and exists rules that skip the tenant', function (): void {
    $source = <<<'PHP'
        <?php
        use Illuminate\Validation\Rule;
        use Illuminate\Validation\Rules\Unique;
        $rules = ['email' => 'required|email|unique:users,email'];
        $rules = ['user_id' => ['required', 'exists:users,id']];
        $rules = ['email' => [Rule::unique('users')]];
        $rules = ['user_id' => [\Illuminate\Validation\Rule::exists(User::class, 'id')]];
        $rules = ['email' => [new Unique('users', 'email')]];
        $rules = ['user_id' => [new \Illuminate\Validation\Rules\Exists('users')]];
        PHP;

    expect(TenancyArchitecture::untenantedValidationRulesIn($source))->toBe([
        "line 4: rule string 'required|email|unique:users,email'",
        "line 5: rule string 'exists:users,id'",
        'line 6: Rule::unique',
        'line 7: Rule::exists',
        'line 8: new Unique',
        'line 9: new Exists',
    ]);
});

it('does not report tenant-aware or unrelated validation rules', function (): void {
    $source = <<<'PHP'
        <?php
        $rules = ['email' => ['required', 'email', TenantRule::unique(User::class, 'email')]];
        $rules = ['member_id' => [TenantRule::exists(User::class, 'id')]];
        $rules = ['items' => ['array', 'distinct']];
        $message = 'This value must be unique: pick another.';
        $collection->unique();
        File::exists($path);
        PHP;

    expect(TenancyArchitecture::untenantedValidationRulesIn($source))->toBe([]);
});

it('puts every route that loads the signed-in user behind the tenant middleware', function (): void {
    app(Kernel::class);
    $router = app('router');

    expect($router->getRoutes()->getRoutesByName())->toHaveKeys(['login', 'dashboard', 'members.index'])
        ->and(TenancyArchitecture::routesOutsideTenant($router, $router->getRoutes()->getRoutes()))->toBe([]);
});

it('reports a route that loads the signed-in user outside a tenant', function (): void {
    app(Kernel::class);
    $router = app('router');

    $routes = [
        Route::middleware(['web', 'auth'])->get('/_arch/auth', fn () => 'ok'),
        Route::middleware(['web', 'guest'])->post('/_arch/guest', fn () => 'ok'),
        Route::middleware(['web'])->get('/_arch/can', fn () => 'ok')->can('viewAny', User::class),
        Route::middleware(['web', 'tenant', 'auth'])->get('/_arch/tenant', fn () => 'ok'),
        Route::middleware(['web'])->get('/_arch/public', fn () => 'ok'),
    ];

    expect(TenancyArchitecture::routesOutsideTenant($router, $routes))->toBe([
        'GET|HEAD _arch/auth',
        'POST _arch/guest',
        'GET|HEAD _arch/can',
    ]);
});

it('resolves the tenant before the session, authentication and route model binding', function (): void {
    // The HTTP kernel hands its middleware aliases and priority to the router.
    app(Kernel::class);
    $route = Route::middleware(['web', 'auth', 'tenant'])->get('/_tenancy/order', fn () => 'ok');

    $order = array_values(app('router')->gatherRouteMiddleware($route));

    expect($order[0])->toBe(ResolveTenantFromSubdomain::class)
        ->and($order)->toContain(StartSession::class, Authenticate::class, SubstituteBindings::class);
});
