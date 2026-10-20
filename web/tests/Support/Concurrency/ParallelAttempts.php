<?php

declare(strict_types=1);

namespace Tests\Support\Concurrency;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs booking changes in separate PHP processes, each with its own database
 * connection, and releases them at the same moment (tests/Concurrency).
 */
final class ParallelAttempts
{
    public const string NEEDS_POSTGRES = 'Needs PostgreSQL: run through tools/test-postgres.mjs (part of npm run test).';

    /**
     * A skip condition: these tests only mean something on PostgreSQL.
     * Use as ->skip(ParallelAttempts::notOnPostgres(), ParallelAttempts::NEEDS_POSTGRES).
     */
    public static function notOnPostgres(): Closure
    {
        return fn (): bool => DB::getDriverName() !== 'pgsql';
    }

    /**
     * Starts one process per attempt, waits until every process has booted,
     * releases them all at once, and returns what each printed, sorted.
     *
     * @param  list<array{0: 'book'|'cancel'|'join', 1: int, 2: int}>  $attempts  [action, session or booking id, customer id]
     * @param  int  $holdMs  how long each attempt keeps its transaction open after acting
     * @return list<string>
     */
    public static function run(Tenant $tenant, array $attempts, int $holdMs = 300): array
    {
        $goFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'go-'.bin2hex(random_bytes(8));

        $processes = array_map(function (array $attempt) use ($tenant, $goFile, $holdMs): Process {
            [$action, $recordId, $customerId] = $attempt;
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/Concurrency/attempt-booking.php'),
                $action,
                (string) $tenant->id,
                (string) $recordId,
                (string) $customerId,
                $goFile,
                (string) $holdMs,
            ]);
            $process->setTimeout(120);
            $process->start();

            return $process;
        }, $attempts);

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
}
