<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The query builder of every tenant-owned model (D-007). Bulk updates skip
 * model events, so the trait's write guards never see them. This builder
 * refuses any bulk update that writes tenant_id, which would move rows to
 * another tenant. The tenant scope still limits which rows are touched.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
final class TenantBuilder extends Builder
{
    /**
     * @param  array<array-key, mixed>  $values
     * @return int
     */
    public function update(array $values)
    {
        $this->refuseTenantChange(array_keys($values));

        return parent::update($values);
    }

    /**
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<array-key, mixed>  $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->refuseTenantChange([$column, ...array_keys($extra)]);

        return parent::increment($column, $amount, $extra);
    }

    /**
     * @param  string|Expression  $column
     * @param  float|int  $amount
     * @param  array<array-key, mixed>  $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->refuseTenantChange([$column, ...array_keys($extra)]);

        return parent::decrement($column, $amount, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function incrementEach(array $columns, array $extra = [])
    {
        $this->refuseTenantChange([...array_keys($columns), ...array_keys($extra)]);

        return parent::incrementEach($columns, $extra);
    }

    /**
     * @param  array<string, float|int|numeric-string>  $columns
     * @param  array<string, mixed>  $extra
     * @return int
     */
    public function decrementEach(array $columns, array $extra = [])
    {
        $this->refuseTenantChange([...array_keys($columns), ...array_keys($extra)]);

        return parent::decrementEach($columns, $extra);
    }

    /**
     * @param  list<mixed>  $columns  column names, plain (`tenant_id`) or qualified (`users.tenant_id`)
     */
    private function refuseTenantChange(array $columns): void
    {
        foreach ($columns as $column) {
            if (is_string($column) && strtolower(last(explode('.', $column))) === 'tenant_id') {
                throw TenancyViolation::tenantChanged($this->model::class);
            }
        }
    }
}
