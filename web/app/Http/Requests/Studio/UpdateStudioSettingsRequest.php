<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only these fields reach the tenant: never its slug or id.
 */
final class UpdateStudioSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'cancellation_window_hours' => ['required', 'integer', 'min:0', 'max:168'],
        ];
    }
}
