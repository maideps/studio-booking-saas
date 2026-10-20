<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A pending password reset for one email in one tenant (D-008). The token
 * is stored hashed. Written and read only by TenantTokenRepository.
 */
#[Fillable(['email', 'token'])]
#[Hidden(['token'])]
class PasswordResetToken extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;
}
