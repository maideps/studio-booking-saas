<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The studio's members. Authorization is on the route (UserPolicy).
 */
final class MemberController extends Controller
{
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

        return Inertia::render('Members/Index', [
            'members' => $members,
            'roles' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                Role::cases(),
            ),
            'status' => $request->session()->get('status'),
        ]);
    }
}
