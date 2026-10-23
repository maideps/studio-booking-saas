<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A purchase the studio's offer does not allow. The message is safe to show
 * the customer.
 */
final class BillingRefused extends RuntimeException
{
    public static function notOffered(): self
    {
        return new self(__('This studio does not sell that.'));
    }

    public static function alreadyMember(): self
    {
        return new self(__('You already have an active membership.'));
    }
}
