<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Billing\CheckoutKind;
use App\Billing\FakePaymentGateway;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Checkout;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;

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
     * Makes the fixture session full: capacity 1, booked by the member.
     */
    public function fillSession(): void
    {
        $this->tenant->run(function (): void {
            $this->classSession->update(['capacity' => 1]);
            Booking::factory()->create(['class_session_id' => $this->classSession->id, 'customer_id' => $this->member->id]);
        });
    }

    /**
     * The customer's booking in the fixture session, created now.
     */
    public function customerBooking(): Booking
    {
        return $this->tenant->run(fn () => Booking::factory()->create([
            'class_session_id' => $this->classSession->id,
            'customer_id' => $this->customer->id,
        ]));
    }

    /**
     * The customer waiting for the fixture session, which is made full first.
     */
    public function customerWaitlistEntry(): WaitlistEntry
    {
        $this->fillSession();

        return $this->tenant->run(fn () => WaitlistEntry::factory()->create([
            'class_session_id' => $this->classSession->id,
            'customer_id' => $this->customer->id,
        ]));
    }

    /**
     * Makes the studio sell a monthly membership (49.00) and a 10-class pack (90.00).
     */
    public function sellPasses(): void
    {
        $this->tenant->update([
            'membership_price_cents' => 4900,
            'class_pack_size' => 10,
            'class_pack_price_cents' => 9000,
        ]);
    }

    /**
     * A class-pack checkout of the customer, already paid at the gateway.
     */
    public function customerPaidCheckout(): Checkout
    {
        $this->sellPasses();

        return $this->tenant->run(fn () => Checkout::factory()->create([
            'customer_id' => $this->customer->id,
            'gateway_session_id' => FakePaymentGateway::paidSessionId(CheckoutKind::ClassPack),
        ]));
    }

    /**
     * The path with its placeholders replaced by this studio's records.
     *
     * @param  array<string, int|string>  $extra  more placeholders, such as ['{booking}' => 7]
     */
    public function path(string $path, array $extra = []): string
    {
        return strtr($path, [
            '{member}' => (string) $this->member->id,
            '{invitation}' => (string) $this->invitation->id,
            '{classTemplate}' => (string) $this->classTemplate->id,
            '{classSession}' => (string) $this->classSession->id,
            ...array_map(fn (int|string $value): string => (string) $value, $extra),
        ]);
    }

    /**
     * @param  array<string, int|string>  $extra
     */
    public function url(string $path, array $extra = []): string
    {
        return tenantUrl($this->tenant, $this->path($path, $extra));
    }
}
