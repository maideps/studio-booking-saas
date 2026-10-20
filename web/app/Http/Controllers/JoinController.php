<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Members\AcceptInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accepting an invitation from the emailed link, `/join/{token}`.
 *
 * The token is looked up in the current tenant only, so a link only works
 * on the studio that sent it. When the invited email already has an account
 * here, only that account, signed in, can accept; otherwise the invitee
 * creates an account.
 */
final class JoinController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = Invitation::findPending($token) ?? abort(404);
        $signedIn = $request->user();
        $hasAccount = $this->existingAccount($invitation) !== null;

        // An existing member has to log in first; bring them back here afterwards.
        if ($hasAccount && ! $signedIn instanceof User) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return Inertia::render('Join', [
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role->label(),
            'hasAccount' => $hasAccount,
            'signedInAs' => $signedIn instanceof User ? $signedIn->email : null,
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $invitation = Invitation::findPending($token) ?? abort(404);
        $signedIn = $request->user();
        $account = $this->existingAccount($invitation);

        if ($signedIn instanceof User && ($account === null || $signedIn->isNot($account))) {
            abort(403, __('You are signed in as another member. Sign out to accept this invitation.'));
        }

        if ($account !== null) {
            if (! $signedIn instanceof User) {
                return redirect()->guest(route('login'));
            }

            $accept->forExistingMember($invitation, $account);
        } else {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            Auth::login($accept->withNewAccount($invitation, $validated['name'], $validated['password']));
            $request->session()->regenerate();
        }

        return redirect()->route('dashboard')->with('status', __('Welcome to the team.'));
    }

    private function existingAccount(Invitation $invitation): ?User
    {
        return User::query()->where('email', $invitation->email)->first();
    }
}
