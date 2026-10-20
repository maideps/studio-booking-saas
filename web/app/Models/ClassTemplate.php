<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\ClassTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of class the studio offers ("Morning Flow", 60 minutes, 12 places).
 * Sessions are scheduled from it.
 */
#[Fillable(['name', 'description', 'duration_minutes', 'capacity'])]
class ClassTemplate extends Model
{
    /** @use HasFactory<ClassTemplateFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
        ];
    }
}
