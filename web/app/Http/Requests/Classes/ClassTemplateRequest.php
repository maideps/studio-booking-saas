<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use App\Models\ClassTemplate;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Unique;

/**
 * Creating and editing a class template. Authorization is on the routes.
 */
final class ClassTemplateRequest extends FormRequest
{
    /**
     * The name is unique inside the studio only.
     *
     * @return array<string, list<string|Unique>>
     */
    public function rules(): array
    {
        $template = $this->route('classTemplate');

        return [
            'name' => [
                'required', 'string', 'max:100',
                TenantRule::unique(ClassTemplate::class, 'name')->ignore($template instanceof ClassTemplate ? $template->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }
}
