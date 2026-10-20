<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Members\InviteMember;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Owners invite staff and owners, and revoke pending invitations.
 * Authorization is on the routes (InvitationPolicy).
 */
final class InvitationController extends Controller
{
    public function store(InviteMemberRequest $request, InviteMember $invite): RedirectResponse
    {
        $inviter = $request->user();
        assert($inviter instanceof User);

        $invite->handle($inviter, $request->string('email')->toString(), $request->role());

        return redirect()->route('members.index')->with('status', __('Invitation sent.'));
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return redirect()->route('members.index')->with('status', __('Invitation revoked.'));
    }
}
