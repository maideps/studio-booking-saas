<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a user may do inside their studio (tenant). Every user has exactly
 * one role; the policies in app/Policies decide what each role allows.
 */
enum Role: string
{
    /** Runs the studio: staff, settings, plans and billing. */
    case Owner = 'owner';

    /** Publishes classes and manages bookings. */
    case Staff = 'staff';

    /** Books classes. The role every self-registered user gets. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Staff => 'Staff',
            self::Customer => 'Customer',
        };
    }
}
