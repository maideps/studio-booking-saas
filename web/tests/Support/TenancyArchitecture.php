<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Middleware\ResolveTenantFromSubdomain;
use App\Models\Tenant;
use App\Tenancy\BelongsToTenant;
use App\Tenancy\Queue\TenantAwareJob;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpToken;
use ReflectionClass;

/**
 * The checks behind the tenancy architecture tests (D-002, D-007). They live
 * here, apart from the tests, so each check can also be fed a known violation.
 */
final class TenancyArchitecture
{
    /** Models that are central, not tenant-owned. Adding one is a reviewed decision. */
    public const array CENTRAL_MODELS = [Tenant::class];

    /** Queued jobs that run outside any tenant. Adding one is a reviewed decision. */
    public const array CENTRAL_JOBS = [];

    /**
     * Eloquent methods that drop global scopes, skip the model events that
     * fill and guard tenant_id, or reach the connection directly. Matched
     * when called with ->, ?-> or ::. Any method ending in "Quietly" also
     * skips model events and is matched too.
     */
    public const array BYPASS_METHODS = [
        // Remove the tenant scope.
        'withoutGlobalScope', 'withoutGlobalScopes', 'withoutGlobalScopesExcept',
        'newQueryWithoutScope', 'newQueryWithoutScopes', 'newModelQuery', 'newQueryForRestoration',
        // The base query builder, before global scopes are applied.
        'getQuery',
        // Skip the tenant write guards.
        'withoutEvents', 'unsetEventDispatcher', 'flushEventListeners',
        // Bulk inserts never fire model events.
        'insert', 'insertOrIgnore', 'insertGetId', 'insertUsing', 'upsert',
        // Raw SQL through the model's connection.
        'getConnection',
    ];

    /** DB facade calls that run SQL without any Eloquent scope. */
    public const array DB_QUERY_METHODS = [
        'table', 'select', 'selectOne', 'selectResultSets', 'scalar', 'cursor', 'query',
        'insert', 'update', 'delete', 'statement', 'affectingStatement', 'unprepared', 'connection',
    ];

    /** Names that turn model events off, or reach the database outside Eloquent. */
    public const array BYPASS_NAMES = [
        'WithoutModelEvents', 'DatabaseManager', 'ConnectionInterface', 'ConnectionResolverInterface',
        'Illuminate\\Database\\Connection',
    ];

    /**
     * Rule strings that Laravel checks with a plain query, without global
     * scopes: `unique:users,email`, `required|exists:users,id`.
     */
    private const string VALIDATION_RULE_STRING_PATTERN = '/(?:^|\|)\s*(unique|exists):/i';

    /** Middleware that read the signed-in user, so they only work inside a tenant. */
    public const array TENANT_ONLY_MIDDLEWARE = [Authenticate::class, RedirectIfAuthenticated::class, Authorize::class];

    /** Container keys of the database manager and connections. */
    private const string DB_SERVICE_PATTERN = '/^db(\.|$)/';

    /**
     * Scope bypasses in one PHP source, as "line N: call".
     *
     * @return list<string>
     */
    public static function scopeBypassesIn(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));

        $dbNames = ['DB'];
        $found = [];

        foreach ($tokens as $i => $token) {
            $previous = $tokens[$i - 1] ?? null;
            $beforePrevious = $tokens[$i - 2] ?? null;
            $next = $tokens[$i + 1] ?? null;
            $afterNext = $tokens[$i + 2] ?? null;

            $isMemberAccess = $token->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]);

            if ($isMemberAccess && $next !== null
                && ($next->text === '{' || ($next->is(T_VARIABLE) && $afterNext?->text === '('))) {
                $found[] = sprintf('line %d: dynamic call %s%s', $token->line, $token->text, $next->text);

                continue;
            }

            if ($token->is(T_CONSTANT_ENCAPSED_STRING) && $previous?->text === '('
                && in_array($beforePrevious !== null ? self::shortName($beforePrevious) : null, ['app', 'resolve', 'make'], true)
                && preg_match(self::DB_SERVICE_PATTERN, trim($token->text, '\'"')) === 1) {
                $found[] = sprintf('line %d: container %s', $token->line, $token->text);

                continue;
            }

            $name = self::shortName($token);

            if ($name === null) {
                continue;
            }

            // `use Illuminate\Support\Facades\DB as Database;`
            if ($name === 'DB' && $next?->is(T_AS) && $afterNext !== null && $afterNext->is(T_STRING)) {
                $dbNames[] = $afterNext->text;
            }

            $isMethodCall = $previous !== null
                && $previous->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]);

            if ($isMethodCall && (in_array($name, self::BYPASS_METHODS, true) || str_ends_with($name, 'Quietly'))) {
                $found[] = sprintf('line %d: %s%s', $token->line, $previous->text, $name);

                continue;
            }

            $isDbCall = $previous !== null && $previous->is(T_DOUBLE_COLON)
                && $beforePrevious !== null && in_array(self::shortName($beforePrevious), $dbNames, true);

            if ($isDbCall && in_array($name, self::DB_QUERY_METHODS, true)) {
                $found[] = sprintf('line %d: DB::%s', $token->line, $name);

                continue;
            }

            if (! $isMethodCall && (in_array($name, self::BYPASS_NAMES, true) || in_array(ltrim($token->text, '\\'), self::BYPASS_NAMES, true))) {
                $found[] = sprintf('line %d: %s', $token->line, $name);
            }
        }

        return $found;
    }

    /**
     * Scope bypasses in every PHP file under the directories, as
     * "relative/path.php line N: call", skipping the allowed files.
     *
     * @param  list<string>  $directories
     * @param  list<string>  $allowedFiles  absolute paths
     * @return list<string>
     */
    public static function scopeBypassesInDirectories(array $directories, array $allowedFiles): array
    {
        return self::findInDirectories($directories, $allowedFiles, self::scopeBypassesIn(...));
    }

    /**
     * `unique` and `exists` validation rules in one PHP source, as
     * "line N: rule". Laravel checks them with a plain query that skips the
     * tenant scope, so tenant-owned tables must use TenantRule instead.
     *
     * @return list<string>
     */
    public static function untenantedValidationRulesIn(string $source): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($source),
            fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));

        $found = [];

        foreach ($tokens as $i => $token) {
            $previous = $tokens[$i - 1] ?? null;
            $beforePrevious = $tokens[$i - 2] ?? null;
            $name = self::shortName($token);

            if ($token->is(T_CONSTANT_ENCAPSED_STRING)
                && preg_match(self::VALIDATION_RULE_STRING_PATTERN, trim($token->text, '\'"')) === 1) {
                $found[] = sprintf('line %d: rule string %s', $token->line, $token->text);
            } elseif (in_array($name, ['unique', 'exists'], true) && $previous !== null && $previous->is(T_DOUBLE_COLON)
                && $beforePrevious !== null && self::shortName($beforePrevious) === 'Rule') {
                $found[] = sprintf('line %d: Rule::%s', $token->line, $name);
            } elseif (in_array($name, ['Unique', 'Exists'], true) && $previous !== null && $previous->is(T_NEW)) {
                $found[] = sprintf('line %d: new %s', $token->line, $name);
            }
        }

        return $found;
    }

    /**
     * Untenanted validation rules in every PHP file under the directories,
     * as "relative/path.php line N: rule", skipping the allowed files.
     *
     * @param  list<string>  $directories
     * @param  list<string>  $allowedFiles  absolute paths
     * @return list<string>
     */
    public static function untenantedValidationRulesInDirectories(array $directories, array $allowedFiles): array
    {
        return self::findInDirectories($directories, $allowedFiles, self::untenantedValidationRulesIn(...));
    }

    /**
     * The routes with authentication or authorization middleware but no
     * `tenant` middleware, as "METHODS uri". Those middleware load the
     * signed-in user, which needs a tenant.
     *
     * @param  iterable<Route>  $routes
     * @return list<string>
     */
    public static function routesOutsideTenant(Router $router, iterable $routes): array
    {
        $found = [];

        foreach ($routes as $route) {
            $middleware = array_map(
                fn (mixed $entry): string => is_string($entry) ? explode(':', $entry, 2)[0] : '',
                $router->gatherRouteMiddleware($route),
            );

            $needsTenant = array_filter(
                $middleware,
                fn (string $class): bool => collect(self::TENANT_ONLY_MIDDLEWARE)
                    ->contains(fn (string $tenantOnly): bool => is_a($class, $tenantOnly, true)),
            );

            if ($needsTenant !== [] && ! in_array(ResolveTenantFromSubdomain::class, $middleware, true)) {
                $found[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        return $found;
    }

    /**
     * Every class declared under app/ (PSR-4 `App\`).
     *
     * @return list<class-string>
     */
    public static function appClasses(): array
    {
        $classes = [];

        foreach (File::allFiles(app_path()) as $file) {
            $class = 'App\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname());

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * The concrete Eloquent models under app/.
     *
     * @return list<class-string<Model>>
     */
    public static function appModels(): array
    {
        return array_values(array_filter(
            self::appClasses(),
            fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract(),
        ));
    }

    /**
     * The models that are neither central nor using BelongsToTenant.
     *
     * @param  list<class-string>  $models
     * @param  list<class-string>  $centralModels
     * @return list<class-string>
     */
    public static function modelsMissingTenantTrait(array $models, array $centralModels): array
    {
        return array_values(array_filter(
            $models,
            fn (string $model): bool => ! in_array($model, $centralModels, true)
                && ! in_array(BelongsToTenant::class, class_uses_recursive($model), true),
        ));
    }

    /**
     * The queued jobs that are neither central nor tenant-aware.
     *
     * @param  list<class-string>  $classes
     * @param  list<class-string>  $centralJobs
     * @return list<class-string>
     */
    public static function queuedJobsWithoutTenant(array $classes, array $centralJobs): array
    {
        return array_values(array_filter(
            $classes,
            fn (string $class): bool => is_subclass_of($class, ShouldQueue::class)
                && ! (new ReflectionClass($class))->isAbstract()
                && ! is_subclass_of($class, TenantAwareJob::class)
                && ! in_array($class, $centralJobs, true),
        ));
    }

    /**
     * What a tenant-owned table lacks: a tenant_id column, an index that
     * starts with it, or a foreign key from it to tenants.
     *
     * @return list<string>
     */
    public static function tenantColumnProblems(string $table): array
    {
        if (! Schema::hasColumn($table, 'tenant_id')) {
            return ["{$table} has no tenant_id"];
        }

        $problems = [];

        if (! collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['columns'][0] === 'tenant_id')) {
            $problems[] = "{$table}.tenant_id has no index";
        }

        if (! collect(Schema::getForeignKeys($table))->contains(
            fn (array $key): bool => $key['columns'] === ['tenant_id'] && $key['foreign_table'] === 'tenants',
        )) {
            $problems[] = "{$table}.tenant_id does not reference tenants";
        }

        return $problems;
    }

    /**
     * @param  list<string>  $directories
     * @param  list<string>  $allowedFiles  absolute paths
     * @param  Closure(string): list<string>  $check
     * @return list<string>
     */
    private static function findInDirectories(array $directories, array $allowedFiles, Closure $check): array
    {
        $allowed = array_map(self::normalizePath(...), $allowedFiles);
        $found = [];

        foreach ($directories as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $path = self::normalizePath($file->getPathname());

                if ($file->getExtension() !== 'php' || in_array($path, $allowed, true)) {
                    continue;
                }

                foreach ($check($file->getContents()) as $finding) {
                    $found[] = self::relativePath($path).' '.$finding;
                }
            }
        }

        return $found;
    }

    /**
     * The last segment of a name token (`DB`, `\Illuminate\Support\Facades\DB`).
     */
    private static function shortName(PhpToken $token): ?string
    {
        if (! $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            return null;
        }

        $parts = explode('\\', $token->text);

        return end($parts);
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function relativePath(string $path): string
    {
        return str_replace(self::normalizePath(base_path()).'/', '', $path);
    }
}
