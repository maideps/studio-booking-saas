<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Members\ChangeMemberRole;
use App\Http\Requests\Members\UpdateMemberRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Authorization is on the route (`can:updateRole,member`).
 */
final class MemberRoleController extends Controller
{
    public function __invoke(UpdateMemberRoleRequest $request, User $member, ChangeMemberRole $changeRole): RedirectResponse
    {
        $changeRole->handle($member, $request->role());

        return redirect()->route('members.index')->with('status', __('Role updated.'));
    }
}
