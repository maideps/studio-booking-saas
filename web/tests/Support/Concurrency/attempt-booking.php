<?php

declare(strict_types=1);

/*
 * One booking change in its own PHP process, for tests/Concurrency.
 *
 *   php attempt-booking.php book   <tenant id> <session id> <customer id> <go file> <hold ms>
 *   php attempt-booking.php cancel <tenant id> <booking id> <customer id> <go file> <hold ms>
 *   php attempt-booking.php join   <tenant id> <session id> <customer id> <go file> <hold ms>
 *   php attempt-booking.php fulfil <tenant id> <checkout id> <customer id> <go file> <hold ms>
 *
 * Boots the app, prints "ready", waits until the go file exists (so every
 * process acts at the same moment), books or cancels through the real action
 * and keeps the transaction open for <hold ms> afterwards. That widens the race
 * window: without the session lock, the other attempts would read stale state
 * during the hold. Prints "booked", "cancelled", "waiting", "fulfilled",
 * "already fulfilled" or "refused: <reason>".
 */

use App\Actions\Billing\FulfilCheckout;
use App\Actions\Bookings\BookSession;
use App\Actions\Bookings\CancelBooking;
use App\Actions\Bookings\JoinWaitlist;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\Checkout;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $argv */
$action = (string) ($argv[1] ?? '');
$tenantId = (int) ($argv[2] ?? 0);
$recordId = (int) ($argv[3] ?? 0);
$customerId = (int) ($argv[4] ?? 0);
$goFile = (string) ($argv[5] ?? '');
$holdMicroseconds = (int) ($argv[6] ?? 0) * 1000;

$tenant = Tenant::query()->findOrFail($tenantId);

echo $tenant->run(function () use ($action, $recordId, $customerId, $goFile, $holdMicroseconds): string {
    $customer = User::query()->findOrFail($customerId);
    $change = match ($action) {
        'book' => function () use ($recordId, $customer): string {
            app(BookSession::class)->handle(ClassSession::query()->findOrFail($recordId), $customer);

            return 'booked';
        },
        'cancel' => function () use ($recordId): string {
            app(CancelBooking::class)->handle(Booking::query()->findOrFail($recordId));

            return 'cancelled';
        },
        'join' => function () use ($recordId, $customer): string {
            app(JoinWaitlist::class)->handle(ClassSession::query()->findOrFail($recordId), $customer);

            return 'waiting';
        },
        'fulfil' => fn (): string => app(FulfilCheckout::class)->handle(Checkout::query()->findOrFail($recordId))
            ? 'fulfilled'
            : 'already fulfilled',
        default => throw new InvalidArgumentException("Unknown action {$action}."),
    };

    echo "ready\n";
    fflush(STDOUT);

    while (! file_exists($goFile)) {
        usleep(1_000);
    }

    return DB::transaction(function () use ($change, $holdMicroseconds): string {
        try {
            $result = $change();
        } catch (BookingRefused $refused) {
            return 'refused: '.$refused->getMessage();
        }

        usleep($holdMicroseconds);

        return $result;
    });
}), "\n";
