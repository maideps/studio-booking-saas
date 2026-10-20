<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\ClassTemplate;
use App\Models\User;

/**
 * Every member sees the studio's classes; owners and staff manage them.
 */
final class ClassTemplatePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::Owner, Role::Staff);
    }

    public function update(User $actor, ClassTemplate $template): bool
    {
        return $actor->hasRole(Role::Owner, Role::Staff) && $template->belongsToCurrentTenant();
    }
}
