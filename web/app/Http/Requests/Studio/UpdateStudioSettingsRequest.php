<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only these fields reach the tenant: never its slug or id. Prices are
 * entered in currency units (49.00) and stored in cents (D-012); an empty
 * price means the studio does not sell that.
 */
final class UpdateStudioSettingsRequest extends FormRequest
{
    /** Currencies Stripe Checkout accepts that a small studio is likely to use. */
    public const array CURRENCIES = ['eur', 'usd', 'gbp'];

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
            'currency' => ['sometimes', 'required', 'string', 'in:'.implode(',', self::CURRENCIES)],
            'membership_price' => ['nullable', 'numeric', 'min:1', 'max:1000', 'decimal:0,2'],
            'class_pack_size' => ['nullable', 'integer', 'min:1', 'max:100', 'required_with:class_pack_price'],
            'class_pack_price' => ['nullable', 'numeric', 'min:1', 'max:5000', 'decimal:0,2', 'required_with:class_pack_size'],
        ];
    }

    /**
     * The validated settings as tenant attributes.
     *
     * @return array<string, mixed>
     */
    public function tenantAttributes(): array
    {
        $attributes = $this->safe()->only(['name', 'timezone', 'contact_email', 'cancellation_window_hours', 'currency']);

        foreach (['membership_price' => 'membership_price_cents', 'class_pack_price' => 'class_pack_price_cents'] as $field => $column) {
            if ($this->has($field)) {
                $attributes[$column] = self::cents($this->input($field));
            }
        }

        if ($this->has('class_pack_size')) {
            $attributes['class_pack_size'] = $this->filled('class_pack_size') ? $this->integer('class_pack_size') : null;
        }

        return $attributes;
    }

    private static function cents(mixed $price): ?int
    {
        return is_numeric($price) ? (int) round((float) $price * 100) : null;
    }
}
