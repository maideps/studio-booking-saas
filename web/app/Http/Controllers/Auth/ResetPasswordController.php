<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    /**
     * The broker looks up the user and the token inside the current tenant
     * only (TenantTokenRepository), so a token from another tenant never
     * matches.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        // One message for every failure, so the form does not reveal whether
        // the email belongs to a member of this studio.
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __(Password::INVALID_TOKEN),
            ]);
        }

        return redirect()->route('login')->with('status', __(Password::PASSWORD_RESET));
    }
}
