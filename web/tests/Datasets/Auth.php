<?php

declare(strict_types=1);

use App\Enums\Role;
use Tests\Support\ProtectedActions;

dataset('roles', [
    'owner' => Role::Owner,
    'staff' => Role::Staff,
    'customer' => Role::Customer,
]);

dataset('protected actions', fn (): array => ProtectedActions::dataset());

dataset('protected actions by role', fn (): array => ProtectedActions::withRoles());
