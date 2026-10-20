<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Authorization is on the route (`can:updateRole,member`).
 */
final class UpdateMemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', new Enum(Role::class)],
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? Role::Customer;
    }
}
