<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's members and, for owners, pending invitations. Authorization
 * is on the route (UserPolicy).
 */
final class MemberController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        assert($actor instanceof User);

        $members = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role->value,
                'canUpdateRole' => $actor->can('updateRole', $member),
            ]);

        $canInvite = $actor->can('create', Invitation::class);
        $tenant = $this->context->currentOrFail();

        return Inertia::render('Members/Index', [
            'members' => $members,
            'roles' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                Role::cases(),
            ),
            'canInvite' => $canInvite,
            // Pending invitations are only for the people who may manage them.
            'invitations' => $canInvite
                ? Invitation::query()->pending()->orderBy('email')->get()->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->label(),
                    'expiresAt' => $tenant->localTime($invitation->expires_at)->format(Tenant::LOCAL_TIME_FORMAT),
                ])
                : [],
            'status' => $request->session()->get('status'),
        ]);
    }
}
