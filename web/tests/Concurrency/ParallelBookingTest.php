<?php

declare(strict_types=1);

use App\Models\Booking;
use App\Models\ClassSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\StudioFixture;

// Parallel bookings on PostgreSQL, in separate PHP processes that each open
// their own connection (D-005, D-010). Run by tools/test-postgres.mjs.

/**
 * Skips the test on SQLite: these tests only mean something on PostgreSQL.
 */
function needsPostgres(): Closure
{
    return fn (): bool => DB::getDriverName() !== 'pgsql';
}

const NEEDS_POSTGRES = 'Needs PostgreSQL: run through tools/test-postgres.mjs (part of npm run test).';

/**
 * Starts one booking attempt per customer in its own process, releases them
 * all at once, and returns what each one printed.
 *
 * @param  list<User>  $customers
 * @return list<string>
 */
function bookInParallel(Tenant $tenant, ClassSession $session, array $customers, int $holdMs = 300): array
{
    $goFile = tempnam(sys_get_temp_dir(), 'go');
    unlink($goFile);

    $processes = array_map(function (User $customer) use ($tenant, $session, $goFile, $holdMs): Process {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/Concurrency/attempt-booking.php'),
            (string) $tenant->id,
            (string) $session->id,
            (string) $customer->id,
            $goFile,
            (string) $holdMs,
        ]);
        $process->setTimeout(120);
        $process->start();

        return $process;
    }, $customers);

    // Booting Laravel takes a while; release the attempts only when all are ready.
    $deadline = microtime(true) + 90;
    while (collect($processes)->contains(fn (Process $process): bool => ! str_contains($process->getOutput(), 'ready'))) {
        foreach ($processes as $process) {
            if (! $process->isRunning() && ! $process->isSuccessful()) {
                throw new RuntimeException('A booking process failed: '.$process->getErrorOutput().$process->getOutput());
            }
        }
        if (microtime(true) > $deadline) {
            throw new RuntimeException('The booking processes did not get ready in time.');
        }
        usleep(20_000);
    }
    touch($goFile);

    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('A booking process failed: '.$process->getErrorOutput().$process->getOutput());
        }
        $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
        $results[] = (string) end($lines);
    }
    unlink($goFile);

    sort($results);

    return $results;
}

it('books the last place exactly once when eight customers try at the same moment', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $customers = $studio->tenant->run(function () use ($studio): array {
        $studio->classSession->update(['capacity' => 3]);
        Booking::factory()->count(2)->create(['class_session_id' => $studio->classSession->id]);

        return array_values(User::factory()->customer()->count(8)->create()->all());
    });

    $results = bookInParallel($studio->tenant, $studio->classSession, $customers);

    expect(array_count_values($results))->toBe([
        'booked' => 1,
        'refused: This class is full.' => 7,
    ])->and($studio->tenant->run(fn () => Booking::query()->active()->where('class_session_id', $studio->classSession->id)->count()))
        ->toBe(3);
})->skip(needsPostgres(), NEEDS_POSTGRES);

it('books a customer once when the same booking arrives five times at once', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $customer = $studio->customer;

    $results = bookInParallel($studio->tenant, $studio->classSession, array_fill(0, 5, $customer));

    expect(array_count_values($results))->toBe([
        'booked' => 1,
        'refused: You have already booked this class.' => 4,
    ])->and($studio->tenant->run(fn () => Booking::query()->active()->where('customer_id', $customer->id)->count()))
        ->toBe(1);
})->skip(needsPostgres(), NEEDS_POSTGRES);

it('keeps released bookings out of the one-active-booking index on PostgreSQL', function (): void {
    $studio = StudioFixture::create(twoTenants()->a);
    $attributes = ['class_session_id' => $studio->classSession->id, 'customer_id' => $studio->customer->id];

    $studio->tenant->run(function () use ($attributes): void {
        Booking::factory()->released()->create($attributes);
        Booking::factory()->released()->create($attributes);
        Booking::factory()->create($attributes);
    });

    expect(fn () => $studio->tenant->run(fn () => Booking::factory()->create($attributes)))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(needsPostgres(), NEEDS_POSTGRES);
