<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * A studio using the platform. Tenants are central records: this model is
 * not tenant-scoped, and its slug is the tenant's subdomain.
 */
#[Fillable(['name', 'slug', 'timezone', 'contact_email'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * Class times are shown in this timezone and stored in UTC.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'UTC',
    ];

    /** One DNS label: lowercase letters, digits and inner hyphens, at most 63 characters. */
    public const string SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Wall-clock times in forms, as an HTML datetime-local input sends them. */
    public const string LOCAL_TIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * An absolute URL on this tenant's subdomain. The scheme and port come
     * from APP_URL, so it works outside a request too (mail, queued jobs).
     */
    public function url(string $path = '/'): string
    {
        $appUrl = parse_url(config()->string('app.url'));
        $scheme = is_array($appUrl) ? ($appUrl['scheme'] ?? 'http') : 'http';
        $port = is_array($appUrl) && isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return sprintf('%s://%s.%s%s/%s', $scheme, $this->slug, config()->string('tenancy.domain'), $port, ltrim($path, '/'));
    }

    /**
     * A wall-clock time in the studio (`2026-11-02T18:30`, as an HTML
     * datetime-local input sends it), as a UTC instant.
     */
    public function parseLocalTime(string $local): CarbonImmutable
    {
        // "!" zeroes the fields the format does not set (seconds), instead of
        // taking them from the current time.
        $time = CarbonImmutable::createFromFormat('!'.self::LOCAL_TIME_FORMAT, $local, $this->timezone);

        if (! $time instanceof CarbonImmutable) {
            throw new InvalidArgumentException(sprintf('"%s" is not a %s time.', $local, self::LOCAL_TIME_FORMAT));
        }

        return $time->utc();
    }

    /**
     * A stored UTC instant as wall-clock time in the studio.
     */
    public function localTime(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timezone);
    }

    /**
     * Run the callback inside this tenant, then restore the previous tenant.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        return app(TenantContext::class)->run($this, $callback);
    }
}
