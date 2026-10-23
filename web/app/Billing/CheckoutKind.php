<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * What a checkout sells. Studios offer at most one of each (product
 * non-goals: one monthly membership and one class-pack option).
 */
enum CheckoutKind: string
{
    case Membership = 'membership';
    case ClassPack = 'class_pack';
}
