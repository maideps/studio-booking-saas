<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\StaffInvitation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invites an email to the current studio as staff or owner, and emails the
 * link. A new invitation replaces any pending one for the same email, so
 * only the newest link works.
 */
final readonly class InviteMember
{
    public function __construct(private TenantContext $context) {}

    public function handle(User $inviter, string $email, Role $role): Invitation
    {
        $tenant = $this->context->currentOrFail();
        $token = Str::random(40);

        Invitation::query()->where('email', $email)->delete();

        $invitation = new Invitation([
            'email' => $email,
            'role' => $role,
            'token' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ]);
        $invitation->invitedBy()->associate($inviter);
        $invitation->save();

        Notification::route('mail', $email)->notify(new StaffInvitation($invitation, $tenant, $token));

        return $invitation;
    }
}
