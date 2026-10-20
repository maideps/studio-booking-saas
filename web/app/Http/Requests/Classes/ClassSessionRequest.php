<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use App\Enums\Role;
use App\Models\ClassSession;
use App\Models\ClassTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

/**
 * Scheduling or rescheduling a session. Authorization is on the routes.
 *
 * The template and the instructor must belong to this studio, and the
 * instructor must be an owner or staff member. The start is wall-clock time
 * in the studio timezone.
 */
final class ClassSessionRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists|Closure>>
     */
    public function rules(): array
    {
        return [
            'class_template_id' => ['required', 'integer', TenantRule::exists(ClassTemplate::class, 'id')],
            'instructor_id' => [
                'required', 'integer',
                TenantRule::exists(User::class, 'id')->whereIn('role', [Role::Owner->value, Role::Staff->value]),
            ],
            'starts_at' => [
                'bail', 'required', 'string', 'date_format:'.Tenant::LOCAL_TIME_FORMAT,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && $this->tenant()->parseLocalTime($value)->isPast()) {
                        $fail(__('The session must start in the future.'));
                    }
                },
            ],
            'capacity' => [
                'required', 'integer', 'min:1', 'max:500',
                // A rescheduled session keeps a place for everyone already booked.
                function (string $attribute, mixed $value, Closure $fail): void {
                    $session = $this->route('classSession');

                    if ($session instanceof ClassSession && is_numeric($value) && (int) $value < $session->activeBookingCount()) {
                        $fail(__('The capacity cannot be lower than the :count places already booked.', ['count' => $session->activeBookingCount()]));
                    }
                },
            ],
        ];
    }

    public function template(): ClassTemplate
    {
        return ClassTemplate::query()->findOrFail($this->integer('class_template_id'));
    }

    public function instructor(): User
    {
        return User::query()->findOrFail($this->integer('instructor_id'));
    }

    /**
     * The validated capacity, within the bounds the rules enforce.
     *
     * @return int<1, 500>
     */
    public function capacity(): int
    {
        return max(1, min(500, $this->integer('capacity')));
    }

    /**
     * The start as a UTC instant.
     */
    public function startsAt(): CarbonImmutable
    {
        return $this->tenant()->parseLocalTime($this->string('starts_at')->toString());
    }

    private function tenant(): Tenant
    {
        return app(TenantContext::class)->currentOrFail();
    }
}
