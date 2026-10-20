<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Every route that needs a signed-in user, as [method, path]. `{member}` is
 * replaced with the id of a member of the tenant under test.
 *
 * The tests run each action for every role, for a guest and for a user of
 * another tenant. A test fails when a route with `auth` middleware is
 * missing from this list, so a new protected route cannot skip them.
 */
final class ProtectedActions
{
    public const array ALL = [
        'dashboard' => ['GET', '/dashboard'],
        'log out' => ['POST', '/logout'],
        'members list' => ['GET', '/members'],
        'change a role' => ['PATCH', '/members/{member}/role'],
    ];

    public static function path(string $path, int $memberId): string
    {
        return str_replace('{member}', (string) $memberId, $path);
    }
}
