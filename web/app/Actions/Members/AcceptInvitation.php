<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a pending invitation into membership of the current studio.
 *
 * The caller decides who accepts: the existing account with the invited
 * email (signed in), or a new account created here with the given name and
 * password. Either way the invitation is used up.
 */
final readonly class AcceptInvitation
{
    public function __construct(private ChangeMemberRole $changeRole) {}

    /**
     * An invitation only ever adds rights: someone who became an owner after
     * being invited as staff stays an owner.
     */
    public function forExistingMember(Invitation $invitation, User $member): User
    {
        return DB::transaction(function () use ($invitation, $member): User {
            if (! $member->hasRole(Role::Owner)) {
                $this->changeRole->handle($member, $invitation->role);
            }
            $invitation->delete();

            return $member;
        });
    }

    public function withNewAccount(Invitation $invitation, string $name, #[\SensitiveParameter] string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $member = new User(['name' => $name, 'email' => $invitation->email, 'password' => $password]);
            $member->role = $invitation->role;
            $member->email_verified_at = now();
            $member->save();

            $invitation->delete();

            return $member;
        });
    }
}
