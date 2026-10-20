<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled occurrence of a class template, with its own instructor,
 * time and capacity. Times are stored in UTC; the studio timezone is only
 * applied for input and display.
 */
#[Fillable(['starts_at', 'ends_at', 'capacity'])]
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    /**
     * @return BelongsTo<ClassTemplate, $this>
     */
    public function classTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'capacity' => 'integer',
        ];
    }
}
