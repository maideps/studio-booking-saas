<?php

declare(strict_types=1);

/*
 * One booking attempt in its own PHP process, for tests/Concurrency.
 *
 *   php attempt-booking.php <tenant id> <session id> <customer id> <go file> <hold ms>
 *
 * Boots the app, prints "ready", waits until the go file exists (so every
 * process attempts at the same moment), books through BookSession and keeps
 * the transaction open for <hold ms> afterwards. That widens the race window:
 * without the row lock, the other attempts would read the old count during
 * the hold and overbook. Prints "booked" or "refused: <reason>".
 */

use App\Actions\Bookings\BookSession;
use App\Exceptions\BookingRefused;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $argv */
$tenantId = (int) ($argv[1] ?? 0);
$sessionId = (int) ($argv[2] ?? 0);
$customerId = (int) ($argv[3] ?? 0);
$goFile = (string) ($argv[4] ?? '');
$holdMicroseconds = (int) ($argv[5] ?? 0) * 1000;

$tenant = Tenant::query()->findOrFail($tenantId);

echo $tenant->run(function () use ($sessionId, $customerId, $goFile, $holdMicroseconds): string {
    $session = ClassSession::query()->findOrFail($sessionId);
    $customer = User::query()->findOrFail($customerId);

    echo "ready\n";
    fflush(STDOUT);

    while (! file_exists($goFile)) {
        usleep(1_000);
    }

    return DB::transaction(function () use ($session, $customer, $holdMicroseconds): string {
        try {
            app(BookSession::class)->handle($session, $customer);
        } catch (BookingRefused $refused) {
            return 'refused: '.$refused->getMessage();
        }

        usleep($holdMicroseconds);

        return 'booked';
    });
}), "\n";
