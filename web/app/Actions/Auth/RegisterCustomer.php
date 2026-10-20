<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\Role;
use App\Models\User;

/**
 * Creates a customer account in the current tenant. Self-registration never
 * grants another role; owners promote members with ChangeMemberRole.
 */
final class RegisterCustomer
{
    public function handle(string $name, string $email, #[\SensitiveParameter] string $password): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = Role::Customer;
        $user->save();

        return $user;
    }
}
