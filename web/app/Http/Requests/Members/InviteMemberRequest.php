<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Authorization is on the route (`can:create,App\Models\Invitation`).
 */
final class InviteMemberRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Enum|Closure>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                // A customer may be invited to become staff; existing staff and owners are already in.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isStaff = User::query()
                        ->where('email', $value)
                        ->whereIn('role', [Role::Owner, Role::Staff])
                        ->exists();

                    if ($isStaff) {
                        $fail(__('This person is already on the studio staff.'));
                    }
                },
            ],
            'role' => ['required', 'string', (new Enum(Role::class))->only([Role::Staff, Role::Owner])],
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? Role::Staff;
    }
}
