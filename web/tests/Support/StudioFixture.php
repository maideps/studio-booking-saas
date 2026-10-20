<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

/**
 * A studio with one member per role and one record of each kind that a
 * protected route can point at, for the role matrix and isolation tests.
 */
final readonly class StudioFixture
{
    public function __construct(
        public Tenant $tenant,
        public User $owner,
        public User $staff,
        public User $customer,
        public User $member,
        public Invitation $invitation,
        public ClassTemplate $classTemplate,
        public ClassSession $classSession,
    ) {}

    public static function create(Tenant $tenant): self
    {
        return $tenant->run(function () use ($tenant): self {
            $owner = User::factory()->owner()->create(['name' => 'Owner of '.$tenant->slug]);
            $staff = User::factory()->staff()->create(['name' => 'Staff of '.$tenant->slug]);
            $template = ClassTemplate::factory()->create();

            return new self(
                tenant: $tenant,
                owner: $owner,
                staff: $staff,
                customer: User::factory()->customer()->create(),
                member: User::factory()->customer()->create(),
                invitation: Invitation::factory()->create(),
                classTemplate: $template,
                classSession: ClassSession::factory()->create([
                    'class_template_id' => $template->id,
                    'instructor_id' => $staff->id,
                ]),
            );
        });
    }

    public function user(Role $role): User
    {
        return match ($role) {
            Role::Owner => $this->owner,
            Role::Staff => $this->staff,
            Role::Customer => $this->customer,
        };
    }

    /**
     * The path with its placeholders replaced by this studio's records.
     */
    public function path(string $path): string
    {
        return strtr($path, [
            '{member}' => (string) $this->member->id,
            '{invitation}' => (string) $this->invitation->id,
            '{classTemplate}' => (string) $this->classTemplate->id,
            '{classSession}' => (string) $this->classSession->id,
        ]);
    }

    public function url(string $path): string
    {
        return tenantUrl($this->tenant, $this->path($path));
    }
}
