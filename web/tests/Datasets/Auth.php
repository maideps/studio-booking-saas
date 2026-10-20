<?php

declare(strict_types=1);

use App\Enums\Role;
use Tests\Support\ProtectedActions;

dataset('roles', [
    'owner' => Role::Owner,
    'staff' => Role::Staff,
    'customer' => Role::Customer,
]);

dataset('protected actions', ProtectedActions::ALL);
