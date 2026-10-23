<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Checkout;
use App\Models\ClassTemplate;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Props shared with every page.
     *
     * The user and the tenant are only shared inside a tenant. Off a tenant
     * subdomain there is no tenant, and resolving the user would fail closed.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = $this->context->current();
        $user = $tenant !== null ? $request->user() : null;

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'tenant' => $tenant !== null ? ['name' => $tenant->name, 'slug' => $tenant->slug] : null,
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    // What the navigation may offer; every route still authorizes itself.
                    'can' => [
                        'viewMembers' => $user->can('viewAny', User::class),
                        'manageClasses' => $user->can('create', ClassTemplate::class),
                        'updateSettings' => $user->can('update', $tenant),
                        'buy' => $user->can('create', Checkout::class),
                    ],
                ] : null,
            ],
        ];
    }
}
