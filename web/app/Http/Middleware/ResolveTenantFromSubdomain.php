<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the request host, `<slug>.<tenancy.domain>`, and
 * handles the request inside it. Any other host is a 404, so tenant routes
 * never run without a tenant.
 *
 * Registered as the `tenant` route middleware and sorted ahead of every other
 * middleware, so the session, authentication and route model binding all
 * run inside the tenant and see only its records.
 */
final class ResolveTenantFromSubdomain
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantFor($request->getHost()) ?? abort(404);

        return $this->context->run($tenant, fn (): Response => $next($request));
    }

    private function tenantFor(string $host): ?Tenant
    {
        $suffix = '.'.strtolower(config()->string('tenancy.domain'));

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $slug = substr($host, 0, -strlen($suffix));

        if (preg_match(Tenant::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }

        return Tenant::query()->where('slug', $slug)->first();
    }
}
