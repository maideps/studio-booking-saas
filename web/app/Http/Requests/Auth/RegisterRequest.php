<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

final class RegisterRequest extends FormRequest
{
    /**
     * The email only has to be unique inside the current tenant.
     *
     * @return array<string, list<string|Unique|Password>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', TenantRule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
