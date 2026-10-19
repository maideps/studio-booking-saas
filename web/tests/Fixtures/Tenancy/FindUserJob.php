<?php

declare(strict_types=1);

namespace Tests\Fixtures\Tenancy;

use App\Models\User;
use App\Tenancy\Queue\TenantAware;
use App\Tenancy\Queue\TenantAwareJob;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Looks a user up by id and records what it saw, so tests can check which
 * tenant a queued job ran in and what it could read.
 */
final class FindUserJob implements ShouldQueue, TenantAwareJob
{
    use Queueable, TenantAware;

    public const string RESULT_KEY = 'find-user-job.result';

    public function __construct(public int $userId)
    {
        $this->captureTenant();
    }

    public function handle(TenantContext $context): void
    {
        Cache::put(self::RESULT_KEY, [
            'tenant' => $context->currentOrFail()->slug,
            'email' => User::query()->find($this->userId)?->email,
        ]);
    }
}
